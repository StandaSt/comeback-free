import {
  BadRequestException,
  InternalServerErrorException,
  UnauthorizedException,
} from '@nestjs/common';
import {
  Args,
  Mutation,
  Parent,
  Query,
  ResolveProperty,
  Resolver,
} from '@nestjs/graphql';
import { Int } from 'type-graphql';

import RelevantUserService from 'relevantUser/relevantUser.service';
import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import apiErrors from 'config/api/errors';
import resources from 'config/api/resources';
import GlobalSettings from 'globalSettings/globalSettings.entity';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import PreferredDayService from 'preferredDay/preferredDay.service';
import PreferredHour from 'preferredHour/preferredHour.entity';
import PreferredHourService from 'preferredHour/preferredHour.service';
import ShiftHourService from 'shiftHour/shiftHour.service';
import ShiftRoleTypeService from 'shiftRoleType/shiftRoleType.service';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import UserService from 'user/user.service';
import hourIntervalChecker from 'utils/hourIntervalChecker';
import ActionHistoryService from 'actionHistory/actionHistory.service';

import historyName from '../config/api/history';
import getShiftRoleFirstHour from '../utils/getShiftRoleFirstHour';

import HourArg from './args/hour.arg';
import ShiftRole from './shiftRole.entity';
import ShiftRoleService from './shiftRole.service';

@Resolver(() => ShiftRole)
class ShiftRoleResolver {
  constructor(
    private readonly shiftRoleService: ShiftRoleService,
    private readonly shiftRoleTypeService: ShiftRoleTypeService,
    private readonly shiftHourService: ShiftHourService,
    private readonly userService: UserService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly relevantUserService: RelevantUserService,
    private readonly shiftWeekService: ShiftWeekService,
    private readonly preferredHourService: PreferredHourService,
    private readonly preferredDayService: PreferredDayService,
    private readonly actionHistoryService: ActionHistoryService,
  ) {}

  @Query(() => ShiftRole)
  @Secured(resources.weekPlanning.plan, resources.shiftWeekTemplates.edit)
  async shiftRoleFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const shiftRole = await this.shiftRoleService.findById(id);
    if (!shiftRole) throw new BadRequestException();
    const week = await (await shiftRole.shiftDay).shiftWeek;

    if (!(await this.shiftWeekService.canBeSeen(week, userId))) {
      throw new BadRequestException();
    }

    return shiftRole;
  }

  @Mutation(() => ShiftRole)
  @Secured(resources.weekPlanning.plan, resources.shiftWeekTemplates.edit)
  async shiftRoleEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args({ name: 'hours', type: () => [HourArg], nullable: true })
    hours: HourArg[],
    @Args({ name: 'typeId', type: () => Int, nullable: true }) typeId: number,
    @Args({ name: 'halfHour', type: () => Boolean, nullable: true })
    halfHour: boolean,
    @CurrentUser() userId: number,
  ) {
    let shiftRole = await this.shiftRoleService.findById(id);
    if (!shiftRole) throw new BadRequestException();

    const week = await (await shiftRole.shiftDay).shiftWeek;

    if (!(await this.shiftWeekService.canBeEdited(week, userId))) {
      throw new BadRequestException();
    }
    if (typeId) {
      const type = await this.shiftRoleTypeService.findById(typeId);
      if (!type) throw new BadRequestException();

      if ((await shiftRole.type).id !== typeId) {
        if (!(await this.shiftRoleService.isEmpty(shiftRole))) {
          throw new BadRequestException(apiErrors.shiftRole.notEmpty);
        }
      }

      shiftRole.type = Promise.resolve(type);
    }

    if (hours) {
      if (
        (await shiftRole.shiftHours)
          .map(h => h.startHour)
          .sort()
          .toString() !==
        hours
          .map(h => h.startHour)
          .sort()
          .toString()
      ) {
        if (!(await this.shiftRoleService.isEmpty(shiftRole))) {
          throw new BadRequestException(apiErrors.shiftRole.notEmpty);
        }
      }

      const changedHours = await this.shiftRoleService.changeHours(
        shiftRole,
        hours,
      );
      shiftRole = changedHours.shiftRole;

      await this.shiftHourService.remove(changedHours.removedHours);
    }
    if (halfHour !== undefined && halfHour !== null) {
      shiftRole.halfHour = halfHour;
    }

    return this.shiftRoleService.save(shiftRole);
  }

  @Mutation(() => Boolean)
  @Secured(resources.weekPlanning.plan, resources.shiftWeekTemplates.edit)
  async shiftRoleRemove(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const shiftRole = await this.shiftRoleService.findById(id);
    if (!shiftRole) throw new BadRequestException();

    const week = await (await shiftRole.shiftDay).shiftWeek;

    if (!(await this.shiftWeekService.canBeEdited(week, userId))) {
      throw new BadRequestException();
    }

    if (!(await this.shiftRoleService.isEmpty(shiftRole))) {
      throw new BadRequestException(apiErrors.shiftRole.notEmpty);
    }

    await this.shiftRoleService.removeWithDependencies(id);

    this.actionHistoryService.addRecord({
      name: historyName.shiftRole.remove,
      userId,
      additionalData: shiftRole,
    });

    return true;
  }

  @Mutation(() => ShiftRole)
  @Secured(resources.weekPlanning.plan)
  async shiftRoleAssignWorker(
    @Args({ name: 'shiftRoleId', type: () => Int }) shiftRoleId: number,
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'from', type: () => Int }) from: number,
    @Args({ name: 'to', type: () => Int }) to: number,
    @CurrentUser() currentUserId: number,
  ) {
    const shiftRole = await this.shiftRoleService.findById(shiftRoleId);
    if (!shiftRole) throw new BadRequestException();

    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    if (
      !(await this.shiftWeekService.canBeEdited(
        await (await shiftRole.shiftDay).shiftWeek,
        currentUserId,
      ))
    ) {
      throw new UnauthorizedException();
    }

    const currentUser = await this.userService.findById(currentUserId);
    const shiftRoleType = await shiftRole.type;
    if (
      !(await currentUser.dbPlanableShiftRoleTypes).some(
        t => t.id === shiftRoleType.id,
      )
    ) {
      throw new UnauthorizedException();
    }

    const dayStart = await this.globalSettingsService.findByName(
      GlobalSettings.DAY_START,
    );
    if (!dayStart) throw new InternalServerErrorException();

    if (!hourIntervalChecker(from, to, +dayStart.value)) {
      throw new BadRequestException();
    }

    const shiftDay = await shiftRole.shiftDay;
    const shiftWeek = await shiftDay.shiftWeek;
    const shiftHours = await shiftRole.shiftHours;

    const loopHours = async (fn: (hour: number) => void): Promise<void> => {
      for (let hour = from; hour !== to; hour++) {
        if (hour === 24) {
          if (to === 0) break;
          hour = 0;
        }
        await fn(hour);
      }
    };

    await loopHours(hour => {
      const shiftHour = shiftHours.find(sh => sh.startHour === hour);
      if (!shiftHour)
        throw new BadRequestException(apiErrors.shiftRole.hoursOutOfRange);
    });
    await loopHours(async hour => {
      let shiftHour = shiftHours.find(sh => sh.startHour === hour);

      const oldPreferredHour = await shiftHour.preferredHour;
      if (oldPreferredHour && !oldPreferredHour.visible) {
        shiftHour.preferredHour = null;
        shiftHour = await this.shiftHourService.save(shiftHour);
        await this.preferredHourService.delete(oldPreferredHour.id);
      }

      let preferredHour = await this.preferredHourService.getCorrespondingToShiftHour(
        shiftHour,
        user,
        shiftDay.day,
      );

      if (!preferredHour) {
        preferredHour = new PreferredHour();
        preferredHour.preferredDay = Promise.resolve(
          await this.preferredDayService.findByStartDay(
            shiftWeek.startDay,
            shiftDay.day,
            user,
          ),
        );
        preferredHour.visible = false;
        preferredHour.startHour = shiftHour.startHour;
        preferredHour = await this.preferredHourService.save(preferredHour);
      }

      shiftHour.preferredHour = Promise.resolve(preferredHour);
      shiftHour.dbWorker = Promise.resolve(user);
      shiftHour = await this.shiftHourService.save(shiftHour);
    });

    return shiftRole;
  }

  @Mutation(() => ShiftRole)
  @Secured(resources.weekPlanning.plan)
  async shiftRoleUnassignWorker(
    @Args({ name: 'shiftRoleId', type: () => Int }) shiftRoleId: number,
    @Args({ name: 'from', type: () => Int }) from: number,
    @Args({ name: 'to', type: () => Int }) to: number,
    @CurrentUser() userId: number,
  ) {
    const shiftRole = await this.shiftRoleService.findById(shiftRoleId);
    if (!shiftRole) throw new BadRequestException();

    const week = await (await shiftRole.shiftDay).shiftWeek;
    if (!(await this.shiftWeekService.canBeEdited(week, userId))) {
      throw new BadRequestException();
    }

    return this.shiftRoleService.unassignWorker(shiftRoleId, from, to);
  }

  @ResolveProperty(() => Int)
  async firstHour(@Parent() parent: ShiftRole) {
    const dayStart = +(
      await this.globalSettingsService.findByName(GlobalSettings.DAY_START)
    ).value;

    return getShiftRoleFirstHour({
      shiftHours: await parent.shiftHours,
      dayStart,
    });
  }
}

export default ShiftRoleResolver;
