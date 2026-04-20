import { Field, Int, ObjectType } from 'type-graphql';

import PreferredHour from 'preferredHour/preferredHour.entity';
import User from 'user/user.entity';

@ObjectType()
class RelevantUser {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;

  @Field()
  surname: string;

  @Field(() => [PreferredHour])
  preferredHours: PreferredHour[];

  @Field({ nullable: true })
  lastPreferredTime: Date;

  @Field()
  mainBranch: boolean;

  @Field()
  afterDeadline: boolean;

  @Field()
  perfectMatch: boolean;

  @Field(() => Int)
  totalWeekHours: number;

  @Field(() => Int)
  totalPreferredHours: number;

  @Field()
  hasOwnCar: boolean;

  @Field(() => User)
  user: User;
}

export default RelevantUser;
