import { Field, InputType, Int } from 'type-graphql';

@InputType()
class ChangedRoleArg {
  @Field(() => Int)
  resourceId: number;

  @Field(() => Int)
  roleId: number;

  @Field()
  active: boolean;
}

export default ChangedRoleArg;
