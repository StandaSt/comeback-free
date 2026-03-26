import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import ShiftHourModule from 'shiftHour/shiftHour.module';
import ShiftRoleTypeModule from 'shiftRoleType/shiftRoleType.module';
import UserModule from 'user/user.module';
import ActionHistoryModule from 'actionHistory/actionHistory.module';

import PreferredDayModule from '../preferredDay/preferredDay.module';
import PreferredHourModule from '../preferredHour/preferredHour.module';
import RelevantUserModule from '../relevantUser/relevantUser.module';
import ShiftWeekModule from '../shiftWeek/shiftWeek.module';

import ShiftRole from './shiftRole.entity';
import ShiftRoleResolver from './shiftRole.resolver';
import ShiftRoleService from './shiftRole.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftRole]),
    forwardRef(() => AuthModule),
    forwardRef(() => UserModule),
    forwardRef(() => RelevantUserModule),
    ShiftHourModule,
    ShiftRoleTypeModule,
    GlobalSettingsModule,
    PreferredHourModule,
    PreferredDayModule,
    ShiftWeekModule,
    ActionHistoryModule,
  ],
  providers: [ShiftRoleResolver, ShiftRoleService],
  exports: [ShiftRoleService],
})
class ShiftRoleModule {}

export default ShiftRoleModule;
