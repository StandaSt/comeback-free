import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import ShiftDayModule from 'shiftDay/shiftDay.module';
import ShiftHourModule from 'shiftHour/shiftHour.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftWeekTemplateModule from 'shiftWeekTemplate/shiftWeekTemplate.module';
import UserModule from 'user/user.module';
import NotificationModule from 'notification/notification.module';

import ShiftWeek from './shiftWeek.entity';
import ShiftWeekResolver from './shiftWeek.resolver';
import ShiftWeekService from './shiftWeek.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftWeek]),
    forwardRef(() => AuthModule),
    forwardRef(() => ShiftWeekTemplateModule),
    forwardRef(() => UserModule),
    forwardRef(() => ShiftDayModule),
    forwardRef(() => ShiftRoleModule),
    ShiftHourModule,
    NotificationModule,
  ],
  providers: [ShiftWeekService, ShiftWeekResolver],
  exports: [ShiftWeekService],
})
class ShiftWeekModule {}

export default ShiftWeekModule;
