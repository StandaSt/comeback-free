import { Field, InputType, Int } from 'type-graphql';

@InputType()
class TimeNotificationReceiverArg {
  @Field(() => Int, { nullable: true })
  resourceId: number;

  @Field(() => Int, { nullable: true })
  roleId: number;
}

export default TimeNotificationReceiverArg;
