import { Args, Query, ResolveProperty, Resolver } from '@nestjs/graphql';
import { FieldResolver, Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import PaginatorArg from 'paginator/paginator.arg';

import OrderByArg from '../../paginator/orderBy.arg';
import ActionHistory from '../actionHistory.entity';
import ActionHistoryService from '../actionHistory.service';

import ActionHistoryFilterArg, {
  getActionHistoryFilterArgDefaultValue,
} from './args/actionHistoryFilter';
import ActionHistoryPaginator from './actionHistoryPaginator.entity';

@Resolver(() => ActionHistoryPaginator)
class ActionHistoryPaginatorResolver {
  constructor(private readonly actionHistoryService: ActionHistoryService) {}

  @Secured(resources.actionHistory.see)
  @Query(() => ActionHistoryPaginator)
  async actionHistoryPaginate() {
    return new ActionHistoryPaginator();
  }

  @ResolveProperty(() => [ActionHistory])
  async items(
    @Args() paginator: PaginatorArg,
    @Args({
      name: 'filter',
      type: () => ActionHistoryFilterArg,
      nullable: true,
      defaultValue: getActionHistoryFilterArgDefaultValue(),
    })
    filter: ActionHistoryFilterArg,
    @Args({ name: 'orderBy', type: () => OrderByArg, nullable: true })
    orderBy: OrderByArg,
  ) {
    return this.actionHistoryService.paginate(
      paginator.limit,
      paginator.offset,
      filter,
      orderBy,
    );
  }

  @ResolveProperty(() => Int)
  async totalCount(
    @Args({
      name: 'filter',
      type: () => ActionHistoryFilterArg,
      nullable: true,
      defaultValue: getActionHistoryFilterArgDefaultValue(),
    })
    filter: ActionHistoryFilterArg,
  ) {
    return this.actionHistoryService.getTotalCount(filter);
  }
}

export default ActionHistoryPaginatorResolver;
