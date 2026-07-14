import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import Resource from 'resource/resource.entity';
import ResourceResolver from 'resource/resource.resolver';
import ResourceService from 'resource/resource.service';

import AuthModule from '../auth/auth.module';
import RoleModule from '../role/role.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Resource]),
    forwardRef(() => AuthModule),
    forwardRef(() => RoleModule),
  ],
  providers: [ResourceResolver, ResourceService],
  exports: [ResourceService],
})
export default class ResourceModule {}
