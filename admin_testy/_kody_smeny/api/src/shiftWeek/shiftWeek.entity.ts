import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToMany,
  OneToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';

import Branch from 'branch/branch.entity';
import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftWeekTemplate from 'shiftWeekTemplate/shiftWeekTemplate.entity';

@ObjectType()
@Entity()
class ShiftWeek {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field({ nullable: true })
  @Column({ nullable: true })
  startDay: Date;

  @Field()
  @Column({ default: false })
  published: boolean;

  @Field(() => [ShiftDay])
  @OneToMany(() => ShiftDay, shiftDay => shiftDay.shiftWeek)
  shiftDays: Promise<ShiftDay[]>;

  @Field(() => Branch)
  @ManyToOne(() => Branch, branch => branch.dbShiftWeeks)
  branch: Promise<Branch>;

  @Field(() => ShiftWeekTemplate)
  @OneToOne(
    () => ShiftWeekTemplate,
    shiftWeekTemplate => shiftWeekTemplate.shiftWeek,
  )
  shiftWeekTemplate: Promise<ShiftWeekTemplate>;
}

export default ShiftWeek;
