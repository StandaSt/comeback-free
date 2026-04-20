import { Args, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';

import ResourceCategory from './resourceCategory.entity';
import ResourceCategoryService from './resourceCategory.service';

@Resolver()
class ResourceCategoryResolver {
  constructor(
    private readonly resourceCategoryService: ResourceCategoryService,
  ) {}

  @Query(() => [ResourceCategory])
  @Secured(resources.roles.see)
  async resourceCategoryFindAll() {
    return this.resourceCategoryService.findAllAndOrderByLabel();
  }

  @Query(() => ResourceCategory)
  @Secured(resources.roles.see)
  async resourceCategoryFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
  ) {
    return this.resourceCategoryService.findById(id);
  }
}

export default ResourceCategoryResolver;
