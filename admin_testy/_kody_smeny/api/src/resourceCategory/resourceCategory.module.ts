import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';

import ResourceCategory from './resourceCategory.entity';
import ResourceCategoryResolver from './resourceCategory.resolver';
import ResourceCategoryService from './resourceCategory.service';

@Module({
  imports: [TypeOrmModule.forFeature([ResourceCategory]), AuthModule],
  providers: [ResourceCategoryResolver, ResourceCategoryService],
  exports: [ResourceCategoryService],
})
export default class ResourceCategoryModule {}
