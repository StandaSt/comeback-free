import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';

import PreferredDay from 'preferredDay/preferredDay.entity';

import ShiftHour from '../shiftHour/shiftHour.entity';

@ObjectType()
@Entity()
class PreferredHour {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field(() => Int)
  @Column()
  startHour: number;

  @Field(() => Int)
  @Column({ default: true })
  visible: boolean;

  @Field(() => PreferredDay)
  @ManyToOne(() => PreferredDay, preferredDay => preferredDay.preferredHours)
  preferredDay: Promise<PreferredDay>;

  @OneToOne(() => ShiftHour, shiftHour => shiftHour.preferredHour)
  dbShiftHour: Promise<ShiftHour>;
}

export default PreferredHour;
