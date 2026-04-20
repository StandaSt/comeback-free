import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ScheduleModule } from '@nestjs/schedule';

import AuthModule from 'auth/auth.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import PreferredDayModule from 'preferredDay/preferredDay.module';
import UserModule from 'user/user.module';
import PreferredHourModule from 'preferredHour/preferredHour.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';

import NotificationModule from '../notification/notification.module';

import PreferredWeek from './preferredWeek.entity';
import PreferredWeekResolver from './preferredWeek.resolver';
import PreferredWeekService from './preferredWeek.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([PreferredWeek]),
    ScheduleModule.forRoot(),
    forwardRef(() => UserModule),
    forwardRef(() => AuthModule),
    forwardRef(() => PreferredDayModule),
    forwardRef(() => GlobalSettingsModule),
    forwardRef(() => PreferredHourModule),
    forwardRef(() => ShiftWeekModule),
    NotificationModule,
  ],
  providers: [PreferredWeekResolver, PreferredWeekService],
  exports: [PreferredWeekService],
})
class PreferredWeekModule {}

export default PreferredWeekModule;
