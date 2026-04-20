import { Field, Int, ObjectType } from 'type-graphql';
import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';

@ObjectType()
@Entity()
class GlobalSettings {
  static readonly DAY_START = 'dayStart';

  static readonly PREFERRED_WEEKS_AHEAD = 'preferredWeeksAhead';

  static readonly PREFERRED_DEADLINE = 'preferredDeadline';

  static readonly EVALUATION_COOLDOWN = 'evaluationCooldown';

  static readonly EVALUATION_TTL = 'evaluationTTL';

  static readonly DEADLINE_NOTIFICATION = 'deadlineNotification';

  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field()
  @Column({ unique: true })
  name: string;

  @Field()
  @Column()
  value: string;
}

export default GlobalSettings;
