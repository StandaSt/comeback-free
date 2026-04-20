import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import ShiftDayModule from 'shiftDay/shiftDay.module';
import ShiftHourModule from 'shiftHour/shiftHour.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';
import UserModule from 'user/user.module';

import BranchModule from '../branch/branch.module';

import ShiftWeekTemplate from './shiftWeekTemplate.entity';
import ShiftWeekTemplateResolver from './shiftWeekTemplate.resolver';
import ShiftWeekTemplateService from './shiftWeekTemplate.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftWeekTemplate]),
    forwardRef(() => AuthModule),
    forwardRef(() => ShiftDayModule),
    forwardRef(() => UserModule),
    forwardRef(() => ShiftWeekModule),
    forwardRef(() => ShiftDayModule),
    forwardRef(() => ShiftRoleModule),
    ShiftHourModule,
    forwardRef(() => BranchModule),
  ],
  providers: [ShiftWeekTemplateResolver, ShiftWeekTemplateService],
  exports: [ShiftWeekTemplateService],
})
class ShiftWeekTemplateModule {}

export default ShiftWeekTemplateModule;
