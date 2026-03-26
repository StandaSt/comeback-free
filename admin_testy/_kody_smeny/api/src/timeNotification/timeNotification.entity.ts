import { Column, Entity, OneToMany, PrimaryGeneratedColumn } from 'typeorm';
import { Field, Int, ObjectType } from 'type-graphql';

import TimeNotificationReceiverGroup from 'timeNotificationReceiverGroup/timeNotificationReceiverGroup.entity';
import Repeat from 'utils/repeat';

@Entity()
@ObjectType()
class TimeNotification {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column()
  name: string;

  @Field({ nullable: true })
  @Column({ nullable: true })
  date: Date;

  @Field()
  @Column()
  message: string;

  @Field(() => Repeat)
  @Column('int')
  repeat: Repeat;

  @Field(() => [TimeNotificationReceiverGroup])
  @OneToMany(
    () => TimeNotificationReceiverGroup,
    timeNotificationReceiverGroup => timeNotificationReceiverGroup.notification,
  )
  receiverGroups: Promise<TimeNotificationReceiverGroup[]>;
}
export default TimeNotification;
