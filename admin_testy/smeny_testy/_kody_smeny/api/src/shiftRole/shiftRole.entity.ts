import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
} from 'typeorm';

import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftHour from 'shiftHour/shiftHour.entity';
import ShiftRoleType from 'shiftRoleType/shiftRoleType.entity';

@ObjectType()
@Entity()
class ShiftRole {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field({ description: 'Does the first hour starts half hour later' })
  @Column({ default: false })
  halfHour: boolean;

  @Field(() => ShiftRoleType)
  @ManyToOne(() => ShiftRoleType)
  type: Promise<ShiftRoleType>;

  @ManyToOne(() => ShiftDay, shiftDay => shiftDay.shiftRoles)
  @Field(() => ShiftDay)
  shiftDay: Promise<ShiftDay>;

  @Field(() => [ShiftHour])
  @OneToMany(() => ShiftHour, shiftHour => shiftHour.shiftRole)
  shiftHours: Promise<ShiftHour[]>;
}

export default ShiftRole;
