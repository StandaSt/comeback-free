import { BadRequestException } from '@nestjs/common';
import {
  Args,
  Mutation,
  Parent,
  Query,
  ResolveProperty,
  Resolver,
} from '@nestjs/graphql';
import { Int } from 'type-graphql';

import AuthService from 'auth/auth.service';
import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import apiErrors from 'config/api/errors';
import resources from 'config/api/resources';
import { roleNameRegex } from 'config/regexs';
import ResourceService from 'resource/resource.service';
import User from 'user/user.entity';

import Role from './role.entity';
import RoleService from './role.service';

@Resolver(() => Role)
class RoleResolver {
  constructor(
    private readonly roleService: RoleService,
    private readonly resourceService: ResourceService,
    private readonly authService: AuthService,
  ) {}

  @Query(() => [Role])
  @Secured(resources.roles.see, resources.users.see)
  async roleFindAll() {
    return this.roleService.findAll();
  }

  @Query(() => Role)
  @Secured(resources.roles.see)
  async roleFindById(@Args({ name: 'id', type: () => Int }) id: number) {
    return this.roleService.findById(id);
  }

  @Mutation(() => Role)
  @Secured(resources.roles.editRoles)
  async roleCreate(
    @Args('name') name: string,
    @Args({ name: 'maxUsers', type: () => Int }) maxUsers: number,
    @Args('registrationDefault') registrationDefault: boolean,
    @Args({ name: 'sortIndex', type: () => Int }) sortIndex: number,
  ) {
    if (!roleNameRegex.test(name)) {
      return new BadRequestException(apiErrors.input.invalid);
    }
    const role = new Role();
    role.name = name;
    role.maxUsers = maxUsers;
    role.registrationDefault = registrationDefault;
    role.sortIndex = sortIndex;

    return this.roleService.save(role);
  }

  @Mutation(() => Boolean)
  @Secured(resources.roles.editRoles)
  async roleRemove(@Args({ name: 'id', type: () => Int }) id: number) {
    const role = await this.roleService.findById(id);
    if (!role) return new BadRequestException(apiErrors.input.invalid);

    if ((await this.roleService.findAll()).length < 2)
      throw new BadRequestException(apiErrors.remove.roleMinimalCount);

    const res = await this.resourceService.findAll();
    for (const resource of res) {
      const resourceRoles = await resource.roles;
      const roleIndex = resourceRoles.findIndex(r => r.id === id);
      if (roleIndex >= 0) {
        resourceRoles.splice(roleIndex, 1);
        break;
      }
    }
    if (!(await this.resourceService.validate(res))) {
      throw new BadRequestException(apiErrors.remove.resourceConditions);
    }
    await this.roleService.remove(role);

    return true;
  }

  @Mutation(() => Role)
  @Secured(resources.roles.editRoles)
  async roleEdit(
    @Args({ name: 'roleId', type: () => Int }) roleId: number,
    @Args('name') name: string,
    @Args({ name: 'maxUsers', type: () => Int }) maxUsers: number,
    @Args('registrationDefault') registrationDefault: boolean,
    @Args({ name: 'sortIndex', type: () => Int }) sortIndex: number,
  ) {
    if (!roleNameRegex.test(name)) {
      return new BadRequestException(apiErrors.input.invalid);
    }
    const role = await this.roleService.findById(roleId);
    role.name = name;
    role.maxUsers = maxUsers;
    role.registrationDefault = registrationDefault;
    role.sortIndex = sortIndex;

    return this.roleService.save(role);
  }

  @ResolveProperty(() => [User], { nullable: true })
  async users(@Parent() parent: Role, @CurrentUser() userId: number) {
    if (
      await this.authService.hasResources(userId, [
        /* resources.user.seeAll */
      ])
    )
      return parent.dbUsers;

    return null;
  }

  @ResolveProperty(() => Int)
  async userCount(@Parent() parent: Role) {
    return (await parent.dbUsers).length;
  }
}

export default RoleResolver;
