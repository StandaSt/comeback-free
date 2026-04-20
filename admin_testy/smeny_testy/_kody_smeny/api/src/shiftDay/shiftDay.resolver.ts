import { BadRequestException, UnauthorizedException } from '@nestjs/common';
import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import HourArg from 'shiftRole/args/hour.arg';
import ShiftRole from 'shiftRole/shiftRole.entity';
import ShiftRoleService from 'shiftRole/shiftRole.service';
import ShiftRoleTypeService from 'shiftRoleType/shiftRoleType.service';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import ActionHistoryService from 'actionHistory/actionHistory.service';

import historyName from '../config/api/history';

import ShiftDay from './shiftDay.entity';
import ShiftDayService from './shiftDay.service';

@Resolver()
class ShiftDayResolver {
  constructor(
    private readonly shiftDayService: ShiftDayService,
    private readonly shiftRoleService: ShiftRoleService,
    private readonly shiftRoleTypeService: ShiftRoleTypeService,
    private readonly shiftWeekService: ShiftWeekService,
    private readonly actionHistoryService: ActionHistoryService,
  ) {}

  @Query(() => ShiftDay)
  @Secured(resources.weekPlanning.see, resources.shiftWeekTemplates.see)
  async shiftDayFindById(
    @Args({ type: () => Int, name: 'id' }) id: number,
    @CurrentUser() userId: number,
  ) {
    const day = await this.shiftDayService.findById(id);
    if (!day) throw new BadRequestException();
    if (!(await this.shiftWeekService.canBeSeen(await day.shiftWeek, userId))) {
      throw new UnauthorizedException();
    }

    return day;
  }

  @Mutation(() => ShiftDay)
  @Secured()
  async shiftDayAddRole(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args({ name: 'typeId', type: () => Int }) typeId: number,
    @Args({ name: 'hours', type: () => [HourArg], nullable: true })
    hours: HourArg[],
    @Args('halfHour') halfHour: boolean,
    @CurrentUser() userId: number,
  ): Promise<ShiftDay> {
    const shiftDay = await this.shiftDayService.findById(id);
    if (!shiftDay) throw new BadRequestException();

    const week = await shiftDay.shiftWeek;

    if (!(await this.shiftWeekService.canBeEdited(week, userId))) {
      throw new UnauthorizedException();
    }

    const shiftRoleType = await this.shiftRoleTypeService.findById(typeId);
    if (!shiftRoleType) throw new BadRequestException();

    let role = new ShiftRole();
    role.halfHour = halfHour;
    role.type = Promise.resolve(shiftRoleType);
    role.shiftDay = Promise.resolve(shiftDay);

    role = await this.shiftRoleService.save(role);
    if (hours) {
      role = (await this.shiftRoleService.changeHours(role, hours)).shiftRole;
    }

    await this.shiftRoleService.save(role);

    const historyRole = { ...role };
    historyRole.shiftHours = undefined;
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    historyRole.__shiftHours__ = undefined;

    this.actionHistoryService.addRecord({
      name: historyName.shiftRole.add,
      userId,
      additionalData: {
        role: historyRole,
      },
    });

    return this.shiftDayService.findById(id);
  }
}

export default ShiftDayResolver;
