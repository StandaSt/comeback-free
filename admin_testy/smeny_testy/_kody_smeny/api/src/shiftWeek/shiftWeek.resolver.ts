import { BadRequestException } from '@nestjs/common';
import {
  Args,
  Mutation,
  Parent,
  ResolveProperty,
  Resolver,
  Query,
} from '@nestjs/graphql';
import { Int } from 'type-graphql';
import dayjs from 'dayjs';

import notifications from 'config/api/notifications';
import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftHourService from 'shiftHour/shiftHour.service';
import ShiftRoleService from 'shiftRole/shiftRole.service';
import ShiftWeekTemplateService from 'shiftWeekTemplate/shiftWeekTemplate.service';
import Day from 'utils/day';
import NotificationService from 'notification/notification.service';
import routes from 'config/app/routes';
import getNextMonday from 'utils/getNextMonday';
import UserService from 'user/user.service';

import EventNotification from '../eventNotification/eventNotification.entity';

import ShiftWeek from './shiftWeek.entity';
import ShiftWeekService from './shiftWeek.service';

@Resolver(() => ShiftWeek)
class ShiftWeekResolver {
  constructor(
    private readonly shiftWeekService: ShiftWeekService,
    private readonly shiftWeekTemplateService: ShiftWeekTemplateService,
    private readonly shiftHourService: ShiftHourService,
    private readonly shiftRoleService: ShiftRoleService,
    private readonly notificationService: NotificationService,
    private readonly userService: UserService,
  ) {}

  @Query(() => Date)
  @Secured()
  shiftWeekGetStartDay(
    @Args({ name: 'skipWeeks', type: () => Int }) skipWeeks: number,
  ): Date {
    return getNextMonday(skipWeeks);
  }

  @Mutation(() => ShiftWeek)
  @Secured(resources.weekPlanning.publish)
  async shiftWeekPublish(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('publish') publish: boolean,
    @CurrentUser() userId: number,
  ) {
    const week = await this.shiftWeekService.findById(id);
    if (!week) throw new BadRequestException();

    if (!(await this.shiftWeekService.canBeEdited(week, userId, true)))
      throw new BadRequestException();

    week.published = publish;

    if (publish) {
      const branch = await week.branch;
      const workers = await branch.dbWorkers;
      const workersIds = workers.map(w => w.id);

      const dbUsers = await this.userService
        .getQueryBuilder('user')
        .innerJoinAndSelect('user.dbWorkingBranches', 'workingBranch')
        .innerJoinAndSelect(
          'workingBranch.dbShiftWeeks',
          'shiftWeek',
          'shiftWeek.startDay = :startDay AND shiftWeek.published = :false',
          { startDay: week.startDay, false: false },
        )
        .where('user.id IN (:...userIds)', { userIds: workersIds })
        .getMany();

      const notifyUsers = [];

      for (const user of dbUsers) {
        const branches = await user.dbWorkingBranches;
        if (branches.length === 1 && branches[0].id === branch.id) {
          notifyUsers.push(user);
        }
      }

      const weekFromVariable = dayjs(week.startDay).format('D. M.');
      const weekToVariable = dayjs(week.startDay).add(6, 'day').format('D. M.');

      this.notificationService.sendEventNotifications(
        EventNotification.SHIFT_WEEK_PUBLISH,
        notifyUsers,
        getNextMonday().toISOString() === week.startDay.toISOString()
          ? routes.nextWorkingWeek
          : '/',
        { weekFrom: weekFromVariable, weekTo: weekToVariable },
      );
    }

    return this.shiftWeekService.save(week);
  }

  @Mutation(() => ShiftWeek)
  @Secured(resources.weekPlanning.copyFromTemplate)
  async shiftWeekCopyFromTemplate(
    @Args({ name: 'templateId', type: () => Int }) templateId: number,
    @Args({ name: 'weekId', type: () => Int }) weekId: number,
    @CurrentUser() userId: number,
  ) {
    const template = await this.shiftWeekTemplateService.findById(templateId);
    if (!template) throw new BadRequestException();

    const week = await this.shiftWeekService.findById(weekId);
    if (!week) throw new BadRequestException();

    if (!(await this.shiftWeekService.canBeEdited(week, userId)))
      throw new BadRequestException();

    if ((await this.shiftRoleCount(week)) !== 0)
      throw new BadRequestException();

    const newWeek = await this.shiftWeekTemplateService.copyShiftWeekTemplateToShiftWeekTemplate(
      template,
      week,
    );

    return this.shiftWeekService.save(newWeek);
  }

  @Mutation(() => ShiftWeek)
  @Secured(resources.weekPlanning.clear)
  async shiftWeekClear(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const week = await this.shiftWeekService.findById(id);
    if (!week) throw new BadRequestException();

    if (!(await this.shiftWeekService.canBeEdited(week, userId)))
      throw new BadRequestException();

    const weekDays = await week.shiftDays;

    for (const day of weekDays) {
      const shiftRoles = await day.shiftRoles;

      for (const shiftRole of shiftRoles) {
        await this.shiftRoleService.removeWithDependencies(shiftRole.id);
      }
    }

    return this.shiftWeekService.findById(id);
  }

  @ResolveProperty(() => Int)
  async shiftRoleCount(@Parent() parent: ShiftWeek) {
    let count = 0;
    const days = await parent.shiftDays;

    const countDay = async (dayName: Day) => {
      const day: ShiftDay = days.find(d => d.day === dayName);
      const shiftRoles = await day.shiftRoles;
      count += shiftRoles.length;
    };

    await countDay(Day.monday);
    await countDay(Day.tuesday);
    await countDay(Day.wednesday);
    await countDay(Day.thursday);
    await countDay(Day.friday);
    await countDay(Day.saturday);
    await countDay(Day.sunday);

    return count;
  }
}

export default ShiftWeekResolver;
