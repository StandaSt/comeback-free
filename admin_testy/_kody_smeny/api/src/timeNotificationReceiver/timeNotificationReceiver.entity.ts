import { Entity, ManyToOne, PrimaryGeneratedColumn } from 'typeorm';
import { Field, Int, ObjectType } from 'type-graphql';

import TimeNotificationReceiverGroup from 'timeNotificationReceiverGroup/timeNotificationReceiverGroup.entity';
import Role from 'role/role.entity';
import Resource from 'resource/resource.entity';

@Entity()
@ObjectType()
class TimeNotificationReceiver {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field(() => TimeNotificationReceiverGroup)
  @ManyToOne(
    () => TimeNotificationReceiverGroup,
    timeNotificationReceiverGroup => timeNotificationReceiverGroup.receivers,
  )
  receiverGroup: Promise<TimeNotificationReceiverGroup>;

  @Field(() => Role, { nullable: true })
  @ManyToOne(() => Role)
  role: Promise<Role>;

  @Field(() => Resource, { nullable: true })
  @ManyToOne(() => Resource)
  resource: Promise<Resource>;
}

export default TimeNotificationReceiver;
