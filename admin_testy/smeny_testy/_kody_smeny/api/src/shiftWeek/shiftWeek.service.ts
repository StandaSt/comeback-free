import { forwardRef, Inject, Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import AuthService from 'auth/auth.service';
import Branch from 'branch/branch.entity';
import resources from 'config/api/resources';
import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftDayService from 'shiftDay/shiftDay.service';
import UserService from 'user/user.service';
import Day from 'utils/day';

import ShiftWeek from './shiftWeek.entity';

@Injectable()
class ShiftWeekService {
  constructor(
    @InjectRepository(ShiftWeek)
    private readonly shiftWeekRepository: Repository<ShiftWeek>,
    @Inject(forwardRef(() => UserService))
    private readonly userService: UserService,
    @Inject(forwardRef(() => AuthService))
    private readonly authService: AuthService,
    private readonly shiftDayService: ShiftDayService,
  ) {}

  async save(shiftWeek: ShiftWeek) {
    return this.shiftWeekRepository.save(shiftWeek);
  }

  async findById(id: number) {
    return this.shiftWeekRepository.findOne(id);
  }

  async findByBranchIdsAndStartDay(branchIds: number[], startDay: Date) {
    return this.shiftWeekRepository
      .createQueryBuilder('shiftWeek')
      .innerJoin('shiftWeek.branch', 'branch')
      .where('shiftWeek.startDay = :startDay', { startDay })
      .andWhere('branch.id in (:...branchIds)', { branchIds })
      .getMany();
  }

  async findByBranchIdAndStartDay(branchId: number, startDay: Date) {
    return this.shiftWeekRepository
      .createQueryBuilder('shiftWeek')
      .innerJoin('shiftWeek.branch', 'branch')
      .where('shiftWeek.startDay = :startDay', { startDay })
      .andWhere('branch.id = :branchId', { branchId })
      .getOne();
  }

  async canBeEdited(
    shiftWeek: ShiftWeek,
    userId: number,
    ignorePublished = false,
  ): Promise<boolean> {
    const isTemplate = (await shiftWeek.shiftWeekTemplate) !== undefined;

    const user = await this.userService.findById(userId);
    if (!user) return false;

    const userBranches = await user.dbPlanableBranches;
    const shiftWeekBranch = await shiftWeek.branch;

    if (!userBranches.some(b => b.id === shiftWeekBranch.id)) return false;

    const canPlan = await this.authService.hasResources(userId, [
      resources.weekPlanning.plan,
    ]);

    const canEditPublished = await this.authService.hasResources(userId, [
      resources.weekPlanning.planPublished,
    ]);
    const canEditTemplates = await this.authService.hasResources(userId, [
      resources.shiftWeekTemplates.edit,
    ]);

    if (isTemplate) {
      return canEditTemplates;
    }
    if (!canPlan) return false;

    const { published } = shiftWeek;

    return !(published && !ignorePublished && !canEditPublished);
  }

  async canBeSeen(shiftWeek: ShiftWeek, userId: number): Promise<boolean> {
    const user = await this.userService.findById(userId);
    if (!user) return false;

    const branch = await shiftWeek.branch;
    const isTemplate = (await shiftWeek.shiftWeekTemplate) !== undefined;

    const userBranches = await user.dbPlanableBranches;

    const canSee = await this.authService.hasResources(userId, [
      resources.weekPlanning.see,
      resources.weekSummary.see,
    ]);
    const canEditTemplates = await this.authService.hasResources(userId, [
      resources.shiftWeekTemplates.edit,
    ]);

    if (!userBranches.some(b => b.id === branch.id)) return false;
    if (isTemplate) {
      return canEditTemplates;
    }

    return canSee;
  }

  async createNew(startDay: Date, branch: Branch) {
    const getPlainShiftDay = async (day: Day): Promise<ShiftDay> => {
      const shiftDay = new ShiftDay();
      shiftDay.day = day;

      return this.shiftDayService.save(shiftDay);
    };

    const newWeek = new ShiftWeek();
    newWeek.startDay = startDay;
    newWeek.branch = Promise.resolve(branch);

    const days = [];
    days.push(await getPlainShiftDay(Day.monday));
    days.push(await getPlainShiftDay(Day.tuesday));
    days.push(await getPlainShiftDay(Day.wednesday));
    days.push(await getPlainShiftDay(Day.thursday));
    days.push(await getPlainShiftDay(Day.friday));
    days.push(await getPlainShiftDay(Day.sunday));
    days.push(await getPlainShiftDay(Day.saturday));

    newWeek.shiftDays = Promise.resolve(days);

    return this.save(newWeek);
  }
}

export default ShiftWeekService;
