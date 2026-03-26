import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import Role from 'role/role.entity';
import RoleResolver from 'role/role.resolver';
import RoleService from 'role/role.service';

import AuthModule from '../auth/auth.module';
import ResourceModule from '../resource/resource.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Role]),
    forwardRef(() => AuthModule),
    forwardRef(() => ResourceModule),
  ],
  providers: [RoleResolver, RoleService],
  exports: [RoleService],
})
export default class RoleModule {}
