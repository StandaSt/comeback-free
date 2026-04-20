import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinTable,
  ManyToMany,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import ShiftWeek from 'shiftWeek/shiftWeek.entity';
import User from 'user/user.entity';

@ObjectType()
@Entity()
class Branch {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field()
  @Column()
  name: string;

  @Field()
  @Column({ default: true })
  active: boolean;

  @Field()
  @Column({ default: '#FFFFFF' })
  color: string;

  @ManyToMany(() => User, user => user.dbPlanableBranches)
  @JoinTable({ name: 'branch_planners_user' })
  dbPlanners: Promise<User[]>;

  @ManyToMany(() => User, user => user.dbWorkingBranches)
  @JoinTable()
  dbWorkers: Promise<User[]>;

  @Field(() => [ShiftWeek])
  @OneToMany(() => ShiftWeek, shiftWeek => shiftWeek.branch)
  dbShiftWeeks: Promise<ShiftWeek[]>;
}

export default Branch;
