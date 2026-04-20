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
import resources from 'config/api/resources';
import ShiftDayService from 'shiftDay/shiftDay.service';
import ShiftWeek from 'shiftWeek/shiftWeek.entity';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import User from 'user/user.entity';
import getNextMonday from 'utils/getNextMonday';

import Branch from './branch.entity';
import BranchService from './branch.service';

@Resolver(() => Branch)
class BranchResolver {
  constructor(
    private readonly branchService: BranchService,
    private readonly shiftWeekService: ShiftWeekService,
    private readonly shiftDayService: ShiftDayService,
    private readonly authService: AuthService,
  ) {}

  @Query(() => [Branch])
  @Secured(
    resources.branches.see,
    resources.enteredPreferredWeeks.see,
    resources.users.see,
  )
  async branchFindAll() {
    return this.branchService.findAll();
  }

  @Query(() => Branch)
  @Secured(resources.branches.see)
  async branchFindById(@Args({ name: 'id', type: () => Int }) id: number) {
    return this.branchService.findById(id);
  }

  @Query(() => ShiftWeek)
  @Secured(resources.weekPlanning.see, resources.weekSummary.see)
  async branchGetShiftWeek(
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @Args({ name: 'skipWeeks', type: () => Int }) skipWeeks: number,
    @CurrentUser() userId: number,
  ) {
    const branch = await this.branchService.findById(branchId);
    if (!branch) throw new BadRequestException();

    const nextMonday = getNextMonday(skipWeeks);

    const shiftWeek = await this.shiftWeekService.findByBranchIdAndStartDay(
      branchId,
      nextMonday,
    );
    if (!shiftWeek) {
      return this.shiftWeekService.createNew(nextMonday, branch);
    }

    if (!(await this.shiftWeekService.canBeSeen(shiftWeek, userId)))
      throw new BadRequestException();

    return shiftWeek;
  }

  @Query(() => [ShiftWeek])
  @Secured(resources.weekSummary.see)
  async branchGetShiftWeeks(
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @CurrentUser() userId: number,
  ): Promise<ShiftWeek[]> {
    const branch = await this.branchService.findById(branchId);
    if (!branch) {
      throw new BadRequestException();
    }

    const shiftWeek = new ShiftWeek();
    shiftWeek.branch = Promise.resolve(branch);

    if (!(await this.shiftWeekService.canBeSeen(shiftWeek, userId)))
      throw new BadRequestException();

    return branch.dbShiftWeeks;
  }

  @Mutation(() => Branch)
  @Secured(resources.branches.add)
  async branchCreate(@Args('name') name: string, @Args('color') color: string) {
    const branch = new Branch();
    branch.name = name;
    branch.color = color;

    return this.branchService.save(branch);
  }

  @Mutation(() => Branch)
  @Secured(resources.branches.edit)
  async branchEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('name') name: string,
    @Args('color') color: string,
  ) {
    const branch = await this.branchService.findById(id);
    if (!branch) throw new BadRequestException();
    branch.name = name;
    branch.color = color;

    return this.branchService.save(branch);
  }

  @Mutation(() => Branch)
  @Secured(resources.branches.edit)
  async branchActivate(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('active') active: boolean,
  ) {
    const branch = await this.branchService.findById(id);
    if (!branch) throw new BadRequestException();
    branch.active = active;

    return this.branchService.save(branch);
  }

  @ResolveProperty(() => [User])
  async planners(@Parent() parent: Branch, @CurrentUser() userId: number) {
    if (await this.authService.hasResources(userId, [resources.branches.see]))
      return parent.dbPlanners;

    return [];
  }

  @ResolveProperty(() => [User])
  async workers(@Parent() parent: Branch, @CurrentUser() userId: number) {
    if (await this.authService.hasResources(userId, [resources.branches.see]))
      return parent.dbWorkers;

    return [];
  }

  @ResolveProperty(() => [User])
  async shiftWeeks(@Parent() parent: Branch, @CurrentUser() userId: number) {
    if (
      await this.authService.hasResources(userId, [
        resources.weekPlanning.see,
        resources.shiftWeekTemplates.see,
      ])
    )
      return parent.dbPlanners;

    return [];
  }
}

export default BranchResolver;
