import { Field, InputType, Int } from 'type-graphql';

@InputType()
class DayHour {
  @Field(() => Int)
  dayId: number;

  @Field(() => [Int])
  hours: [number];
}

export default DayHour;
