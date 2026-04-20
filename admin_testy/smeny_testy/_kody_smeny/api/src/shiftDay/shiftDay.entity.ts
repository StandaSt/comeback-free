import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import ShiftRole from 'shiftRole/shiftRole.entity';
import ShiftWeek from 'shiftWeek/shiftWeek.entity';
import Day from 'utils/day';

@ObjectType()
@Entity()
class ShiftDay {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field(() => Day)
  @Column('int')
  day: Day;

  @Field(() => ShiftWeek)
  @ManyToOne(() => ShiftWeek, shiftWeek => shiftWeek.shiftDays)
  shiftWeek: Promise<ShiftWeek>;

  @Field(() => [ShiftRole])
  @OneToMany(() => ShiftRole, shiftRole => shiftRole.shiftDay)
  shiftRoles: Promise<ShiftRole[]>;
}

export default ShiftDay;
