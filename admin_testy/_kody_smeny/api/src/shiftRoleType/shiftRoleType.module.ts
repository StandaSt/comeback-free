import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';

import ShiftRoleType from './shiftRoleType.entity';
import ShiftRoleTypeResolver from './shiftRoleType.resolver';
import ShiftRoleTypeService from './shiftRoleType.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftRoleType]),
    forwardRef(() => AuthModule),
  ],
  providers: [ShiftRoleTypeService, ShiftRoleTypeResolver],
  exports: [ShiftRoleTypeService],
})
class ShiftRoleTypeModule {}

export default ShiftRoleTypeModule;
