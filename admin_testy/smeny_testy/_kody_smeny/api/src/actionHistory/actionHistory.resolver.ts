import { Args, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';

import ActionHistory from './actionHistory.entity';
import ActionHistoryService from './actionHistory.service';

@Resolver()
class ActionHistoryResolver {
  constructor(private readonly actionHistoryService: ActionHistoryService) {}

  @Query(() => ActionHistory)
  @Secured(resources.actionHistory.see)
  actionHistoryFindById(@Args({ name: 'id', type: () => Int }) id: number) {
    return this.actionHistoryService.findById(id);
  }
}

export default ActionHistoryResolver;
