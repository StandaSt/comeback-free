import { ObjectType } from 'type-graphql';

import Paginator from 'paginator/paginator.type';

import ActionHistory from '../actionHistory.entity';

@ObjectType()
class ActionHistoryPaginator extends Paginator(ActionHistory) {}

export default ActionHistoryPaginator;
