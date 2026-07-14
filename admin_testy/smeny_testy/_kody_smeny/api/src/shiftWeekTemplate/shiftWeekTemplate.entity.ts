import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinColumn,
  OneToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';

import ShiftWeek from 'shiftWeek/shiftWeek.entity';

@ObjectType()
@Entity()
class ShiftWeekTemplate {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column()
  name: string;

  @Field()
  @Column({ default: true })
  active: boolean;

  @Field(() => ShiftWeek)
  @OneToOne(() => ShiftWeek, shiftWeek => shiftWeek.shiftWeekTemplate)
  @JoinColumn()
  shiftWeek: Promise<ShiftWeek>;
}

export default ShiftWeekTemplate;
