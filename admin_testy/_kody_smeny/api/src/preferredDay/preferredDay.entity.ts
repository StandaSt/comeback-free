import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import PreferredHour from 'preferredHour/preferredHour.entity';
import PreferredWeek from 'preferredWeek/preferredWeek.entity';
import Day from 'utils/day';

@ObjectType()
@Entity()
class PreferredDay {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field(() => Day)
  @Column('int')
  day: Day;

  @Field(() => PreferredWeek)
  @ManyToOne(() => PreferredWeek, preferredWeek => preferredWeek.preferredDays)
  preferredWeek: Promise<PreferredWeek>;

  @Field(() => [PreferredHour])
  @OneToMany(() => PreferredHour, preferredHour => preferredHour.preferredDay)
  preferredHours: Promise<PreferredHour[]>;
}

export default PreferredDay;
