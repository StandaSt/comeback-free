import { Column, Entity, ManyToOne, PrimaryGeneratedColumn } from 'typeorm';
import { Field, Int, ObjectType } from 'type-graphql';

import User from 'user/user.entity';

@Entity()
@ObjectType()
class Notification {
  @PrimaryGeneratedColumn()
  @Field(() => Int)
  id: number;

  @Column({ type: 'text' })
  @Field()
  subscription: string;

  @ManyToOne(() => User, user => user.notifications)
  user: Promise<User>;
}

export default Notification;
