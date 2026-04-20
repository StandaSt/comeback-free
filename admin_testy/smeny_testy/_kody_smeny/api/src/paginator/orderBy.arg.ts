import { Field, InputType } from 'type-graphql';

import GraphqlOrderType, { OrderType } from './orderType.scalar';

@InputType()
class OrderByArg {
  @Field()
  fieldName: string;

  @Field(() => GraphqlOrderType)
  type: OrderType;
}

export default OrderByArg;
