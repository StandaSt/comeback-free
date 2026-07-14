import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinTable,
  ManyToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import Resource from 'resource/resource.entity';
import User from 'user/user.entity';

@ObjectType()
@Entity()
class Role {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column({ unique: true })
  name: string;

  @Field(() => Int)
  @Column({ default: 99999 })
  maxUsers: number;

  @Field()
  @Column({ default: false })
  registrationDefault: boolean;

  @Field(() => Int)
  @Column({ default: 0 })
  sortIndex: number;

  @Field(() => [Resource], { nullable: true })
  @ManyToMany(() => Resource, resource => resource.roles)
  @JoinTable()
  resources: Promise<Resource[]>;

  @ManyToMany(() => User, user => user.roles)
  dbUsers: Promise<User[]>;
}

export default Role;
