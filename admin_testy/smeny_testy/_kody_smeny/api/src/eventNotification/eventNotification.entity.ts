// eslint-disable-next-line max-classes-per-file
import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';
import { Field, Int, ObjectType } from 'type-graphql';

@ObjectType()
export class EventNotificationVariable {
  @Field()
  value: string;

  @Field()
  description: string;
}

@Entity()
@ObjectType()
class EventNotification {
  static readonly SHIFT_WEEK_PUBLISH = 'shiftWeekPublish';

  static readonly NEW_USER_REGISTRATION = 'newUserRegistration';

  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field()
  @Column({ unique: true })
  eventName: string;

  @Field()
  @Column()
  message: string;

  @Field()
  @Column()
  label: string;

  @Field()
  @Column()
  description: string;

  @Column({ type: 'text', nullable: true })
  variables: string;
}

export default EventNotification;
