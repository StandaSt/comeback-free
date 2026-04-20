import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftRoleTypeModule from 'shiftRoleType/shiftRoleType.module';
import ActionHistoryModule from 'actionHistory/actionHistory.module';

import ShiftWeekModule from '../shiftWeek/shiftWeek.module';

import ShiftDay from './shiftDay.entity';
import ShiftDayResolver from './shiftDay.resolver';
import ShiftDayService from './shiftDay.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftDay]),
    forwardRef(() => AuthModule),
    forwardRef(() => ShiftRoleModule),
    ShiftRoleTypeModule,
    ShiftWeekModule,
    ActionHistoryModule,
  ],
  providers: [ShiftDayResolver, ShiftDayService],
  exports: [ShiftDayService],
})
class ShiftDayModule {}

export default ShiftDayModule;
