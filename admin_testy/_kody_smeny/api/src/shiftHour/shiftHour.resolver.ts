import { Parent, ResolveProperty, Resolver } from '@nestjs/graphql';

import AuthService from 'auth/auth.service';
import CurrentUser from 'auth/currentUser.decorator';
import User from 'user/user.entity';
import getShiftRoleFirstHour from 'utils/getShiftRoleFirstHour';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import GlobalSetting from 'globalSettings/globalSettings.entity';

import ShiftHour from './shiftHour.entity';

@Resolver(() => ShiftHour)
class ShiftHourResolver {
  constructor(
    private readonly authService: AuthService,
    private readonly globalSettingsService: GlobalSettingsService,
  ) {}

  @ResolveProperty(() => User, { nullable: true })
  async employee(@Parent() parent: ShiftHour, @CurrentUser() userId: number) {
    if (
      !(await this.authService.hasResources(userId, [
        /* resources.user.seeAll */
      ]))
    )
      return [];

    return parent.dbWorker;
  }

  @ResolveProperty(() => Boolean)
  async confirmed(@Parent() parent: ShiftHour): Promise<boolean> {
    return (
      (await (await (await parent?.preferredHour)?.preferredDay)?.preferredWeek)
        ?.confirmed || false
    );
  }

  @ResolveProperty(() => Boolean, {
    nullable: true,
    description: 'Is the shiftHour first in shiftRole after dayStart',
  })
  async isFirst(@Parent() parent: ShiftHour): Promise<boolean> {
    const shiftRole = await parent.shiftRole;
    const dayStart = +(
      await this.globalSettingsService.findByName(GlobalSetting.DAY_START)
    ).value;

    return (
      getShiftRoleFirstHour({
        shiftHours: await shiftRole.shiftHours,
        dayStart,
      }) === parent.startHour
    );
  }
}

export default ShiftHourResolver;
