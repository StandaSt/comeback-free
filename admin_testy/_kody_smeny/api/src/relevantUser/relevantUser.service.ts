import { Injectable } from '@nestjs/common';

import GlobalSettings from 'globalSettings/globalSettings.entity';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import PreferredHourService from 'preferredHour/preferredHour.service';
import PreferredWeek from 'preferredWeek/preferredWeek.entity';
import PreferredWeekService from 'preferredWeek/preferredWeek.service';
import ShiftHourService from 'shiftHour/shiftHour.service';
import ShiftRole from 'shiftRole/shiftRole.entity';

import resources from '../config/api/resources';

import RelevantUser from './relevantUser.entity';

@Injectable()
class RelevantUserService {
  constructor(
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly preferredWeekService: PreferredWeekService,
    private readonly shiftHourService: ShiftHourService,
    private readonly preferredHourService: PreferredHourService,
  ) {}

  async getRelevantToShiftRole(
    shiftRole: ShiftRole,
    startHour: number,
    endHour: number,
    withoutPreferredHours = false,
  ): Promise<RelevantUser[]> {
    const shiftDay = await shiftRole.shiftDay;
    const shiftWeek = await shiftDay.shiftWeek;
    const branch = await shiftWeek.branch;
    const shiftRoleType = await shiftRole.type;
    const { startDay } = shiftWeek;

    const requiredHours = [];
    for (let i = startHour; i !== endHour; i++) {
      if (i === 24) {
        i = 0;
        if (endHour === 0) break;
      }
      requiredHours.push(i);
    }

    const qb = this.preferredWeekService.getQueryBuilder('preferredWeek');

    const relevantPreferredWeeksQuery = await qb
      .innerJoinAndSelect(
        'preferredWeek.preferredDays',
        'preferredDay',
        'preferredDay.day = :day',
        {
          day: shiftDay.day,
        },
      )
      // Can't be andSelect - breaks preferredHours
      .leftJoin('preferredDay.preferredHours', 'preferredHour')
      .innerJoinAndSelect('preferredWeek.user', 'user')
      .innerJoin('user.roles', 'role')
      .leftJoin('user.dbWorkingBranches', 'workingBranch')
      .innerJoin('role.resources', 'resource')
      .innerJoin('user.dbWorkersShiftRoleTypes', 'shiftRoleType')
      .where('preferredWeek.startDay = :startDay', { startDay })
      .andWhere('shiftRoleType.id = :typeId', { typeId: shiftRoleType.id })
      .andWhere('resource.name = :canWork', {
        canWork: resources.preferredWeeks.see,
      })
      .andWhere('workingBranch.id = :id', { id: branch.id })
      .andWhere('user.active = :active', { active: true })
      .andWhere(
        `preferredWeek.id NOT IN${qb
          .subQuery()
          .from(PreferredWeek, 'subPreferredWeek')
          .select('subPreferredWeek.id')
          .innerJoin(
            'subPreferredWeek.preferredDays',
            'subPreferredDay',
            'subPreferredDay.day = :day',
            {
              day: shiftDay.day,
            },
          )
          .innerJoin(
            'subPreferredDay.preferredHours',
            'subPreferredHour',
            'subPreferredHour.startHour IN (:...hours)',
            { hours: requiredHours },
          )
          .leftJoin('subPreferredHour.dbShiftHour', 'subShiftHour')
          .where('subPreferredWeek.startDay = :startDay', { startDay })
          .andWhere('subShiftHour.id IS NOT NULL')
          .andWhere('shiftRoleType.id = :typeId', { typeId: shiftRoleType.id })
          .getQuery()}`,
      );

    let relevantPreferredWeeks: PreferredWeek[] = [];
    if (!withoutPreferredHours) {
      relevantPreferredWeeks = await relevantPreferredWeeksQuery
        .andWhere('preferredHour.startHour IN (:...hours)', {
          hours: requiredHours,
        })
        .getMany();
    } else {
      relevantPreferredWeeks = await relevantPreferredWeeksQuery.getMany();
    }

    const relevantUsers: RelevantUser[] = [];

    for (const relevantPreferredWeek of relevantPreferredWeeks) {
      const user = await relevantPreferredWeek.user;
      const userMainBranch = await user.dbMainBranch;
      const preferredDays = await relevantPreferredWeek.preferredDays;
      const preferredDay = preferredDays.find(day => day.day === shiftDay.day);
      const deadline = new Date(
        (
          await this.globalSettingsService.findByName(
            GlobalSettings.PREFERRED_DEADLINE,
          )
        ).value,
      );

      const currentDeadline = relevantPreferredWeek.startDay;
      currentDeadline.setDate(
        currentDeadline.getDate() + deadline.getDay() - 7,
      );
      currentDeadline.setHours(currentDeadline.getHours());
      currentDeadline.setMinutes(currentDeadline.getMinutes());

      let afterDeadline = false;
      if (!withoutPreferredHours)
        afterDeadline =
          relevantPreferredWeek.lastEditTime.getTime() >
          currentDeadline.getTime();

      const preferredHours = await preferredDay.preferredHours;

      let mainBranch = false;
      if (userMainBranch) {
        mainBranch =
          (await user.dbMainBranch).id === (await shiftWeek.branch).id;
      }

      let perfectMatch = true;
      if (!withoutPreferredHours) {
        for (const hour of requiredHours) {
          if (
            !preferredHours.some(
              preferredHour => preferredHour.startHour === hour,
            )
          ) {
            perfectMatch = false;
            break;
          }
        }
      } else {
        perfectMatch = false;
      }

      const totalWeekHours = await this.shiftHourService
        .getQueryBuilder('shiftHour')
        .innerJoin('shiftHour.dbWorker', 'worker')
        .innerJoin('shiftHour.shiftRole', 'shiftRole')
        .innerJoin('shiftRole.shiftDay', 'shiftDay')
        .innerJoin('shiftDay.shiftWeek', 'shiftWeek')
        .where('worker.id = :id', { id: user.id })
        .andWhere('shiftWeek.startDay = :startDay', { startDay })
        .getCount();

      const totalPreferredHours = await this.preferredHourService
        .getQueryBuilder('preferredHour')
        .leftJoin('preferredHour.preferredDay', 'preferredDay')
        .leftJoin('preferredDay.preferredWeek', 'preferredWeek')
        .where('preferredWeek.id = :id', { id: relevantPreferredWeek.id })
        .andWhere('preferredHour.visible = :true', { true: true })
        .getCount();

      const relevantUser = new RelevantUser();
      relevantUser.id = user.id;
      relevantUser.name = user.name;
      relevantUser.surname = user.surname;
      relevantUser.preferredHours = preferredHours;
      relevantUser.lastPreferredTime = relevantPreferredWeek.lastEditTime;
      relevantUser.mainBranch = mainBranch;
      relevantUser.afterDeadline = afterDeadline;
      relevantUser.perfectMatch = perfectMatch;
      relevantUser.totalWeekHours = totalWeekHours;
      relevantUser.totalPreferredHours = totalPreferredHours;
      relevantUser.hasOwnCar = Boolean(user.hasOwnCar);
      relevantUser.user = await relevantPreferredWeek.user;
      relevantUsers.push(relevantUser);
    }

    return relevantUsers;
  }

  async sort(relevantUsers: RelevantUser[]) {
    const getPoints = (user: RelevantUser) => {
      let points = 0;

      if (user.perfectMatch) {
        points += 5000;
      }
      if (user.mainBranch) {
        points += 10000;
      }
      points -= user.totalWeekHours;

      return points;
    };

    relevantUsers.sort((a, b) => {
      const aPoints = getPoints(a);
      const bPoints = getPoints(b);

      return bPoints - aPoints;
    });

    return relevantUsers;
  }
}

export default RelevantUserService;
