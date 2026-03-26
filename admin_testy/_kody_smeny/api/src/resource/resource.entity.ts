import { Field, Int, ObjectType } from 'type-graphql';
import {
  Column,
  Entity,
  JoinTable,
  ManyToMany,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';

import ResourceCategory from 'resourceCategory/resourceCategory.entity';
import Role from 'role/role.entity';

@ObjectType()
@Entity()
class Resource {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  readonly id: number;

  @Field()
  @Column({ unique: true })
  name: string;

  @Field()
  @Column()
  label: string;

  @Field()
  @Column()
  description: string;

  @Field(() => ResourceCategory, { nullable: true })
  @ManyToOne(() => ResourceCategory, category => category.resources, {
    nullable: false,
  })
  category: Promise<ResourceCategory>;

  @Field(() => Int)
  @Column({ default: 0 })
  minimalCount: number;

  @Field(() => [Resource], { nullable: true })
  @ManyToMany(() => Resource, resource => resource.requiredBy)
  @JoinTable()
  requires: Promise<Resource[]>;

  @Field(() => [Resource], { nullable: true })
  @ManyToMany(() => Resource, resource => resource.requires)
  requiredBy: Promise<Resource[]>;

  @Field(() => [Role], { nullable: true })
  @ManyToMany(() => Role, role => role.resources)
  roles: Promise<Role[]>;
}

export default Resource;
