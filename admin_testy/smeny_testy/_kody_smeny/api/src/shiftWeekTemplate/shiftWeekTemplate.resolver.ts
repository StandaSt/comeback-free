import {
  BadRequestException,
  InternalServerErrorException,
} from '@nestjs/common';
import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import BranchService from 'branch/branch.service';
import resources from 'config/api/resources';
import ShiftDayService from 'shiftDay/shiftDay.service';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import UserService from 'user/user.service';

import ShiftWeekTemplate from './shiftWeekTemplate.entity';
import ShiftWeekTemplateService from './shiftWeekTemplate.service';

@Resolver()
class ShiftWeekTemplateResolver {
  constructor(
    private readonly shiftWeekTemplateService: ShiftWeekTemplateService,
    private readonly shiftDayService: ShiftDayService,
    private readonly userService: UserService,
    private readonly branchService: BranchService,
    private readonly shiftWeekService: ShiftWeekService,
  ) {}

  @Query(() => ShiftWeekTemplate)
  @Secured(resources.shiftWeekTemplates.edit)
  async shiftWeekTemplateFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const template = await this.shiftWeekTemplateService.findById(id);
    if (!template) throw new BadRequestException();

    if (
      !(await this.shiftWeekTemplateService.canAccessTemplate(
        (await (await template.shiftWeek).branch).id,
        userId,
      ))
    ) {
      throw new BadRequestException();
    }

    return template;
  }

  @Query(() => [ShiftWeekTemplate])
  @Secured(resources.shiftWeekTemplates.see)
  async shiftWeekTemplateFindAll(@CurrentUser() userId: number) {
    return this.shiftWeekTemplateService.findByUser(userId);
  }

  @Query(() => [ShiftWeekTemplate])
  @Secured(resources.weekPlanning.copyFromTemplate)
  async shiftWeekTemplateFindByBranchId(
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @CurrentUser() userId: number,
  ) {
    if (
      !(await this.shiftWeekTemplateService.canAccessTemplate(branchId, userId))
    ) {
      throw new BadRequestException();
    }

    return this.shiftWeekTemplateService.findByBranchId(branchId);
  }

  @Mutation(() => ShiftWeekTemplate)
  @Secured(resources.shiftWeekTemplates.add)
  async shiftWeekTemplateCreate(
    @Args('name') name: string,
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @CurrentUser() userId: number,
  ) {
    const template = new ShiftWeekTemplate();
    const user = await this.userService.findById(userId);
    if (!user) throw new InternalServerErrorException();

    const branch = await this.branchService.findById(branchId);
    if (!branch) throw new BadRequestException();

    if (
      !(await this.shiftWeekTemplateService.canAccessTemplate(branchId, userId))
    )
      throw new BadRequestException();

    template.name = name;
    const shiftWeek = await this.shiftWeekService.createNew(null, branch);

    template.shiftWeek = Promise.resolve(shiftWeek);

    return this.shiftWeekTemplateService.save(template);
  }

  @Mutation(() => ShiftWeekTemplate)
  @Secured(resources.shiftWeekTemplates.delete)
  async shiftWeekTemplateRemove(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const template = await this.shiftWeekTemplateService.findById(id);
    if (!template) throw new BadRequestException();

    if (
      !(await this.shiftWeekTemplateService.canAccessTemplate(
        (await (await template.shiftWeek).branch).id,
        userId,
      ))
    ) {
      throw new BadRequestException();
    }

    template.active = false;

    return this.shiftWeekTemplateService.save(template);
  }

  @Mutation(() => ShiftWeekTemplate)
  @Secured(resources.shiftWeekTemplates.edit)
  async shiftWeekTemplateEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @Args('name') name: string,
    @CurrentUser() userId: number,
  ) {
    const template = await this.shiftWeekTemplateService.findById(id);
    if (!template) throw new BadRequestException();

    const branch = await this.branchService.findById(branchId);

    if (
      !(await this.shiftWeekTemplateService.canAccessTemplate(
        (await (await template.shiftWeek).branch).id,
        userId,
      ))
    ) {
      throw new BadRequestException();
    }

    const shiftWeek = await template.shiftWeek;
    shiftWeek.branch = Promise.resolve(branch);

    template.name = name;
    template.shiftWeek = Promise.resolve(
      await this.shiftWeekService.save(shiftWeek),
    );

    return this.shiftWeekTemplateService.save(template);
  }
}

export default ShiftWeekTemplateResolver;
