import { Field, ObjectType } from 'type-graphql';
import { Column, Entity, ManyToOne, PrimaryGeneratedColumn } from 'typeorm';

import User from 'user/user.entity';

@ObjectType()
@Entity()
class ActionHistory {
  @Field()
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column()
  name: string;

  @Field(() => User)
  @ManyToOne(() => User)
  user: Promise<User>;

  @Field()
  @Column()
  date: Date;

  @Field({ nullable: true })
  @Column({ nullable: true, type: 'text' })
  additionalData: string;
}

export default ActionHistory;
