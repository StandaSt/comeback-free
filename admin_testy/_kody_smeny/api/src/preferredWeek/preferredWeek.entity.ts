import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import PreferredDay from 'preferredDay/preferredDay.entity';
import User from 'user/user.entity';

@ObjectType()
@Entity()
class PreferredWeek {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field(() => User)
  @ManyToOne(() => User, user => user.dbPreferredWeeks)
  user: Promise<User>;

  @Field({ nullable: true })
  @Column({ nullable: true })
  lastEditTime: Date;

  @Field()
  @Column({ nullable: false })
  startDay: Date;

  @Field()
  @Column({ default: false })
  confirmed: boolean;

  @Field(() => [PreferredDay])
  @OneToMany(() => PreferredDay, preferredDay => preferredDay.preferredWeek)
  preferredDays: Promise<PreferredDay[]>;
}

export default PreferredWeek;
