import {
  BadRequestException,
  InternalServerErrorException,
} from '@nestjs/common';
import { Args, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import GlobalSettings from 'globalSettings/globalSettings.entity';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import ShiftRoleService from 'shiftRole/shiftRole.service';
import UserService from 'user/user.service';
import hourIntervalChecker from 'utils/hourIntervalChecker';

import RelevantUser from './relevantUser.entity';
import RelevantUserService from './relevantUser.service';

@Resolver()
class RelevantUserResolver {
  constructor(
    private readonly userService: UserService,
    private readonly shiftRoleService: ShiftRoleService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly relevantUserService: RelevantUserService,
  ) {}

  @Query(() => [RelevantUser])
  @Secured()
  async relevantUserFindAllForShiftRole(
    @Args({ name: 'shiftRoleId', type: () => Int }) shiftRoleId: number,
    @Args({ name: 'startHour', type: () => Int }) startHour: number,
    @Args({ name: 'endHour', type: () => Int }) endHour: number,
    @Args({
      name: 'withoutPreferredHours',
      type: () => Boolean,
      defaultValue: false,
    })
    withoutPreferredHours: boolean,
  ) {
    const shiftRole = await this.shiftRoleService.findById(shiftRoleId);
    if (!shiftRole) throw new BadRequestException();

    const dayStart = await this.globalSettingsService.findByName(
      GlobalSettings.DAY_START,
    );
    if (!dayStart) throw new InternalServerErrorException();

    if (!hourIntervalChecker(startHour, endHour, +dayStart.value))
      throw new BadRequestException();

    if (startHour === endHour) throw new BadRequestException();

    const relevantUsers = await this.relevantUserService.getRelevantToShiftRole(
      shiftRole,
      startHour,
      endHour,
      withoutPreferredHours,
    );

    return this.relevantUserService.sort(relevantUsers);
  }
}

export default RelevantUserResolver;
