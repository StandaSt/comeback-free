import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import PreferredHourModule from 'preferredHour/preferredHour.module';
import PreferredWeekModule from 'preferredWeek/preferredWeek.module';
import ActionHistoryModule from 'actionHistory/actionHistory.module';

import PreferredDay from './preferredDay.entity';
import PreferredDayResolver from './preferredDay.resolver';
import PreferredDayService from './preferredDay.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([PreferredDay]),
    forwardRef(() => AuthModule),
    PreferredHourModule,
    forwardRef(() => PreferredWeekModule),
    forwardRef(() => ActionHistoryModule),
  ],
  providers: [PreferredDayResolver, PreferredDayService],
  exports: [PreferredDayService],
})
class PreferredDayModule {}

export default PreferredDayModule;
