import { Field, Int, ObjectType } from 'type-graphql';
import { Column, Entity, OneToMany, PrimaryGeneratedColumn } from 'typeorm';

import Resource from 'resource/resource.entity';

@ObjectType()
@Entity()
class ResourceCategory {
  @Field(() => Int)
  @PrimaryGeneratedColumn()
  id: number;

  @Field()
  @Column({ unique: true })
  name: string;

  @Field()
  @Column()
  label: string;

  @Field(() => [Resource])
  @OneToMany(() => Resource, resource => resource.category)
  resources: Promise<Resource[]>;
}

export default ResourceCategory;
