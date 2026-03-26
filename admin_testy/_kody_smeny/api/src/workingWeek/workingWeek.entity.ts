import { Field, Int, ObjectType } from 'type-graphql';

import PreferredWeek from '../preferredWeek/preferredWeek.entity';

@ObjectType()
export class WorkingInterval {
  @Field(() => Int)
  from: number;

  @Field(() => Int)
  to: number;

  @Field()
  halfHour: boolean;

  @Field()
  branchName: string;

  @Field()
  shiftRoleType: string;
}

@ObjectType()
export class WorkingDay {
  @Field(() => [WorkingInterval])
  workingIntervals: WorkingInterval[];
}

@ObjectType()
class WorkingWeek {
  @Field(() => Int)
  totalBranchCount: number;

  @Field(() => [String])
  publishedBranches: string[];

  @Field(() => WorkingDay)
  monday: WorkingDay;

  @Field(() => WorkingDay)
  tuesday: WorkingDay;

  @Field(() => WorkingDay)
  wednesday: WorkingDay;

  @Field(() => WorkingDay)
  thursday: WorkingDay;

  @Field(() => WorkingDay)
  friday: WorkingDay;

  @Field(() => WorkingDay)
  saturday: WorkingDay;

  @Field(() => WorkingDay)
  sunday: WorkingDay;

  @Field(() => PreferredWeek)
  preferredWeek: PreferredWeek;
}

export default WorkingWeek;
