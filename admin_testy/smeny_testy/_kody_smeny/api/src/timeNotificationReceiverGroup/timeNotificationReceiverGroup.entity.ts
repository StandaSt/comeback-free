import { Entity, ManyToOne, OneToMany, PrimaryGeneratedColumn } from 'typeorm';
import { Field, Int, ObjectType } from 'type-graphql';

import TimeNotification from 'timeNotification/timeNotification.entity';
import TimeNotificationReceiver from 'timeNotificationReceiver/timeNotificationReceiver.entity';

@Entity()
@ObjectType()
class TimeNotificationReceiverGroup {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field(() => TimeNotification)
  @ManyToOne(
    () => TimeNotification,
    timeNotification => timeNotification.receiverGroups,
  )
  notification: Promise<TimeNotification>;

  @Field(() => [TimeNotificationReceiver])
  @OneToMany(
    () => TimeNotificationReceiver,
    timeNotificationReceiver => timeNotificationReceiver.receiverGroup,
  )
  receivers: Promise<TimeNotificationReceiver[]>;
}

export default TimeNotificationReceiverGroup;
