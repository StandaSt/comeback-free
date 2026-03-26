import { forwardRef, Module } from '@nestjs/common';

import AuthModule from 'auth/auth.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import PreferredHourModule from 'preferredHour/preferredHour.module';
import PreferredWeekModule from 'preferredWeek/preferredWeek.module';
import ShiftHourModule from 'shiftHour/shiftHour.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';
import UserModule from 'user/user.module';

import RelevantUserResolver from './relevantUser.resolver';
import RelevantUserService from './relevantUser.service';

@Module({
  imports: [
    forwardRef(() => AuthModule),
    forwardRef(() => UserModule),
    forwardRef(() => ShiftRoleModule),
    forwardRef(() => ShiftWeekModule),
    forwardRef(() => PreferredWeekModule),
    PreferredHourModule,
    ShiftHourModule,
    GlobalSettingsModule,
  ],
  providers: [RelevantUserResolver, RelevantUserService],
  exports: [RelevantUserService],
})
class RelevantUserModule {}

export default RelevantUserModule;
