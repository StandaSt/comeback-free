import { Column, Entity, ManyToOne, PrimaryGeneratedColumn } from 'typeorm';
import { Field, ObjectType } from 'type-graphql';

import User from 'user/user.entity';

@Entity()
@ObjectType()
class Evaluation {
  @Field()
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column()
  positive: boolean;

  @Column()
  date: Date;

  @Field()
  @Column({ nullable: true, type: 'text' })
  description: string;

  @ManyToOne(() => User, user => user.evaluation)
  user: Promise<User>;

  @ManyToOne(() => User, user => user.evaluated)
  evaluater: Promise<User>;
}

export default Evaluation;
