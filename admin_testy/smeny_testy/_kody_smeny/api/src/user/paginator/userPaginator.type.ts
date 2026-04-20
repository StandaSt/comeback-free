import { ObjectType } from 'type-graphql';

import Paginator from 'paginator/paginator.type';
import User from 'user/user.entity';

@ObjectType()
class UserPaginator extends Paginator(User) {}

export default UserPaginator;
