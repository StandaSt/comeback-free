import { Args, Query, ResolveProperty, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import OrderByArg from 'paginator/orderBy.arg';
import PaginatorArg from 'paginator/paginator.arg';
import UserFilterArg, {
  getUserFilterArgDefaultValue,
} from 'user/paginator/args/userFilter.arg';
import UserPaginator from 'user/paginator/userPaginator.type';
import User from 'user/user.entity';
import UserService from 'user/user.service';

@Resolver(() => UserPaginator)
class UserPaginatorResolver {
  constructor(private readonly userService: UserService) {}

  @Query(() => UserPaginator)
  @Secured(resources.users.see)
  async userPaginate() {
    return new UserPaginator();
  }

  @ResolveProperty(() => [User])
  async items(
    @Args() paginator: PaginatorArg,
    @Args({
      name: 'filter',
      type: () => UserFilterArg,
      nullable: true,
      defaultValue: getUserFilterArgDefaultValue(),
    })
    filter: UserFilterArg,
    @Args({ name: 'orderBy', type: () => OrderByArg, nullable: true })
    orderBy: OrderByArg,
  ) {
    return this.userService.paginate(
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
      type: () => UserFilterArg,
      nullable: true,
      defaultValue: getUserFilterArgDefaultValue(),
    })
    filter: UserFilterArg,
  ) {
    return this.userService.getTotalCount(filter);
  }
}

export default UserPaginatorResolver;
