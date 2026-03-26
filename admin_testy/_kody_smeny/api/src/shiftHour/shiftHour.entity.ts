import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';

import User from 'user/user.entity';

import PreferredHour from '../preferredHour/preferredHour.entity';
import ShiftRole from '../shiftRole/shiftRole.entity';

@ObjectType()
@Entity()
class ShiftHour {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field(() => Int)
  @Column()
  startHour: number;

  @Field(() => ShiftRole)
  @ManyToOne(() => ShiftRole, shiftRole => shiftRole.shiftHours)
  shiftRole: Promise<ShiftRole>;

  @ManyToOne(() => User, user => user.dbShiftHours)
  @JoinColumn()
  dbWorker: Promise<User>;

  @OneToOne(() => PreferredHour, preferredHour => preferredHour.dbShiftHour)
  @JoinColumn()
  preferredHour: Promise<PreferredHour>;
}

export default ShiftHour;
