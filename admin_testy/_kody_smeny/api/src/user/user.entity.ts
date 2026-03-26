import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinTable,
  ManyToMany,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import Role from 'role/role.entity';
import Notification from 'notification/notification.entity';
import Branch from 'branch/branch.entity';
import PreferredWeek from 'preferredWeek/preferredWeek.entity';
import ShiftHour from 'shiftHour/shiftHour.entity';
import ShiftRoleType from 'shiftRoleType/shiftRoleType.entity';
import Evaluation from 'evaluation/evalution.entity';

@ObjectType()
@Entity()
class User {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column({ default: true })
  active: boolean;

  @Field()
  @Column({ default: true })
  approved: boolean;

  @Field()
  @Column()
  createTime: Date;

  @Field({ nullable: true })
  @Column({ nullable: true })
  lastLoginTime: Date;

  @Field({ nullable: true })
  accessToken: string;

  @Field({ nullable: true })
  @Column({ unique: true })
  email: string;

  @Column({ default: true })
  passwordIsHashed: boolean;

  @Column()
  password: string;

  @Field({ nullable: true })
  generatedPassword: string;

  @Field(() => [Role], { nullable: true })
  @ManyToMany(() => Role, role => role.dbUsers)
  @JoinTable()
  roles: Promise<Role[]>;

  @Field()
  @Column()
  name: string;

  @Field()
  @Column()
  surname: string;

  @Field()
  @Column({ default: false })
  darkTheme: boolean;

  @Field({ nullable: true })
  @Column({ nullable: true })
  hasOwnCar?: boolean;

  @Field({ nullable: true })
  @Column({ nullable: true })
  phoneNumber: string;

  @Field()
  @Column({ default: false })
  receiveEmails: boolean;

  @ManyToMany(() => Branch, branch => branch.dbPlanners)
  dbPlanableBranches: Promise<Branch[]>;

  @ManyToMany(() => ShiftRoleType, shiftRoleType => shiftRoleType.dbPlanners)
  dbPlanableShiftRoleTypes: Promise<ShiftRoleType[]>;

  @ManyToMany(() => Branch, branch => branch.dbWorkers)
  dbWorkingBranches: Promise<Branch[]>;

  @ManyToMany(() => ShiftRoleType, shiftRoleType => shiftRoleType.dbWorkers)
  @JoinTable()
  dbWorkersShiftRoleTypes: Promise<ShiftRoleType[]>;

  @OneToMany(() => PreferredWeek, preferredWeek => preferredWeek.user)
  dbPreferredWeeks: Promise<PreferredWeek[]>;

  @OneToMany(() => ShiftHour, shiftHour => shiftHour.dbWorker)
  dbShiftHours: Promise<[ShiftHour]>;

  @ManyToOne(() => Branch)
  dbMainBranch: Promise<Branch>;

  @OneToMany(() => Evaluation, evaluation => evaluation.user)
  evaluation: Promise<Evaluation[]>;

  @OneToMany(() => Evaluation, evaluation => evaluation.evaluater)
  evaluated: Promise<Evaluation[]>;

  @OneToMany(() => Notification, notification => notification.user)
  notifications: Promise<Notification[]>;
}

export default User;
