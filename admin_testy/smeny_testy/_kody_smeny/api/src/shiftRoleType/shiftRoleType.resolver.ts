import { BadRequestException } from '@nestjs/common';
import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';

import resources from '../config/api/resources';

import ShiftRoleType from './shiftRoleType.entity';
import ShiftRoleTypeService from './shiftRoleType.service';

@Resolver()
class ShiftRoleTypeResolver {
  constructor(private readonly shiftRoleTypeService: ShiftRoleTypeService) {}

  @Query(() => [ShiftRoleType])
  @Secured(
    resources.shiftRoleTypes.see,
    resources.weekPlanning.plan,
    resources.shiftWeekTemplates.edit,
    resources.users.see,
  )
  async shiftRoleTypeFindAll() {
    return this.shiftRoleTypeService.findAllActive();
  }

  @Query(() => ShiftRoleType)
  @Secured(resources.shiftRoleTypes.see)
  async shiftRoleTypeFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
  ) {
    return this.shiftRoleTypeService.findById(id);
  }

  @Mutation(() => ShiftRoleType)
  @Secured(resources.shiftRoleTypes.add)
  async shiftRoleTypeCreate(
    @Args('name') name: string,
    @Args('registrationDefault') registrationDefault: boolean,
    @Args({ name: 'sortIndex', type: () => Int }) sortIndex: number,
    @Args('color') color: string,
  ) {
    const type = new ShiftRoleType();
    type.name = name;
    type.registrationDefault = registrationDefault;
    type.sortIndex = sortIndex;
    type.color = color;

    return this.shiftRoleTypeService.save(type);
  }

  @Mutation(() => ShiftRoleType)
  @Secured(resources.shiftRoleTypes.delete)
  async shiftRoleTypeDeactivate(
    @Args({ name: 'id', type: () => Int }) id: number,
  ) {
    const type = await this.shiftRoleTypeService.findById(id);
    if (!type) throw new BadRequestException();
    type.active = false;

    return this.shiftRoleTypeService.save(type);
  }

  @Mutation(() => ShiftRoleType)
  @Secured(resources.shiftRoleTypes.edit)
  async shiftRoleTypeEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('name') name: string,
    @Args('registrationDefault') registrationDefault: boolean,
    @Args({ name: 'sortIndex', type: () => Int }) sortIndex: number,
    @Args('color') color: string,
    @Args('useCars') useCars: boolean,
  ) {
    const type = await this.shiftRoleTypeService.findById(id);
    if (!type) throw new BadRequestException();
    type.name = name;
    type.registrationDefault = registrationDefault;
    type.sortIndex = sortIndex;
    type.color = color;
    type.useCars = useCars;

    return this.shiftRoleTypeService.save(type);
  }
}

export default ShiftRoleTypeResolver;
