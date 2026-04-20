import { forwardRef, Module } from '@nestjs/common';

import AuthModule from 'auth/auth.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';
import UserModule from 'user/user.module';
import PreferredWeekModule from 'preferredWeek/preferredWeek.module';

import WorkingWeekResolver from './workingWeek.resolver';

@Module({
  imports: [
    forwardRef(() => AuthModule),
    UserModule,
    ShiftWeekModule,
    GlobalSettingsModule,
    PreferredWeekModule,
  ],
  providers: [WorkingWeekResolver],
})
class WorkingWeekModule {}

export default WorkingWeekModule;
