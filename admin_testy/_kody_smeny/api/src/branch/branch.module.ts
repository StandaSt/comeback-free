import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import ShiftDayModule from 'shiftDay/shiftDay.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';

import Branch from './branch.entity';
import BranchResolver from './branch.resolver';
import BranchService from './branch.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([Branch]),
    forwardRef(() => AuthModule),
    forwardRef(() => ShiftWeekModule),
    ShiftDayModule,
  ],
  providers: [BranchResolver, BranchService],
  exports: [BranchService],
})
class BranchModule {}

export default BranchModule;
