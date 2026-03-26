import { Field, InputType, Int } from 'type-graphql';

@InputType()
class HourArg {
  @Field(() => Int)
  startHour: number;
}

export default HourArg;
