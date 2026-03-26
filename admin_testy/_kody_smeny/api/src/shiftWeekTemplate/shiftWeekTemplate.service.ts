import { Injectable, InternalServerErrorException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftDayService from 'shiftDay/shiftDay.service';
import ShiftHour from 'shiftHour/shiftHour.entity';
import ShiftHourService from 'shiftHour/shiftHour.service';
import ShiftRole from 'shiftRole/shiftRole.entity';
import ShiftRoleService from 'shiftRole/shiftRole.service';
import ShiftWeek from 'shiftWeek/shiftWeek.entity';
import UserService from 'user/user.service';

import ShiftWeekTemplate from './shiftWeekTemplate.entity';

@Injectable()
class ShiftWeekTemplateService {
  constructor(
    @InjectRepository(ShiftWeekTemplate)
    private readonly shiftWeekTemplateRepository: Repository<ShiftWeekTemplate>,
    private readonly shiftHourService: ShiftHourService,
    private readonly shiftRoleService: ShiftRoleService,
    private readonly shiftDayService: ShiftDayService,
    private readonly userService: UserService,
  ) {}

  async save(shiftWeekTemplate: ShiftWeekTemplate) {
    return this.shiftWeekTemplateRepository.save(shiftWeekTemplate);
  }

  async findById(id: number) {
    return this.shiftWeekTemplateRepository.findOne({ id, active: true });
  }

  async findByBranchId(branchId: number) {
    return this.shiftWeekTemplateRepository
      .createQueryBuilder('template')
      .innerJoin('template.shiftWeek', 'shiftWeek')
      .innerJoin('shiftWeek.branch', 'branch')
      .where('branch.id  = :branchId', { branchId })
      .andWhere('template.active = :active', { active: true })
      .getMany();
  }

  async findByUser(userId: number) {
    const user = await this.userService.findById(userId);
    if (!user) throw new InternalServerErrorException();

    const userBranches = await user.dbPlanableBranches;
    const userBranchesIds = userBranches.map(branch => branch.id);

    return this.shiftWeekTemplateRepository
      .createQueryBuilder('template')
      .innerJoin('template.shiftWeek', 'shiftWeek')
      .innerJoin('shiftWeek.branch', 'branch')
      .where('branch.id in (:...userBranches)', {
        userBranches: userBranchesIds,
      })
      .andWhere('template.active = :active', { active: true })
      .getMany();
  }

  async copyShiftWeekTemplateToShiftWeekTemplate(
    shiftWeekTemplate: ShiftWeekTemplate,
    shiftWeek: ShiftWeek,
  ): Promise<ShiftWeek> {
    const copyWeek = shiftWeek;
    const copyDays = [];
    const shiftWeekTemplateShiftWeek = await shiftWeekTemplate.shiftWeek;
    for (const shiftDay of await shiftWeekTemplateShiftWeek.shiftDays) {
      const copyDay = new ShiftDay();

      const copyDayRoles = [];
      for (const shiftRole of await shiftDay.shiftRoles) {
        const copyRole = new ShiftRole();
        copyRole.type = Promise.resolve(await shiftRole.type);

        const copyRoleHours = [];
        for (const shiftHour of await shiftRole.shiftHours) {
          const copyHour = new ShiftHour();
          copyHour.startHour = shiftHour.startHour;
          copyRoleHours.push(await this.shiftHourService.save(copyHour));
        }
        copyRole.shiftHours = Promise.resolve(copyRoleHours);
        copyDayRoles.push(await this.shiftRoleService.save(copyRole));
      }
      copyDay.shiftRoles = Promise.resolve(copyDayRoles);
      copyDay.day = shiftDay.day;
      copyDays.push(await this.shiftDayService.save(copyDay));
    }
    copyWeek.shiftDays = Promise.resolve(copyDays);

    return copyWeek;
  }

  async canAccessTemplate(templateBranchId: number, userId: number) {
    const user = await this.userService.findById(userId);
    if (!user) return false;

    const userBranches = await user.dbPlanableBranches;

    return userBranches.some(userBranch => userBranch.id === templateBranchId);
  }
}

export default ShiftWeekTemplateService;
