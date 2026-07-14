import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinTable,
  ManyToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import User from 'user/user.entity';

@ObjectType()
@Entity()
class ShiftRoleType {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field()
  @Column()
  name: string;

  @Column({ default: true })
  active: boolean;

  @Field()
  @Column({ default: false })
  registrationDefault: boolean;

  @Field(() => Int)
  @Column({ default: 0 })
  sortIndex: number;

  @Field()
  @Column({ default: '#FFFFFF' })
  color: string;

  @Field()
  @Column({ default: false })
  useCars: boolean;

  @ManyToMany(() => User, user => user.dbWorkersShiftRoleTypes)
  dbWorkers: Promise<User[]>;

  @ManyToMany(() => User, user => user.dbPlanableShiftRoleTypes)
  @JoinTable()
  dbPlanners: Promise<User[]>;
}

export default ShiftRoleType;
