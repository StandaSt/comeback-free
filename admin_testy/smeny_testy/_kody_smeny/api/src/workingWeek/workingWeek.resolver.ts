import {
  BadRequestException,
  InternalServerErrorException,
} from '@nestjs/common';
import { Args, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import GlobalSettings from 'globalSettings/globalSettings.entity';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import ShiftDay from 'shiftDay/shiftDay.entity';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import UserService from 'user/user.service';
import Day from 'utils/day';
import dayList from 'utils/dayList';
import getNextMonday from 'utils/getNextMonday';

import PreferredWeekService from '../preferredWeek/preferredWeek.service';
import getShiftRoleFirstHour from '../utils/getShiftRoleFirstHour';

import WorkingWeek, { WorkingDay } from './workingWeek.entity';

@Resolver()
class WorkingWeekResolver {
  constructor(
    private readonly userService: UserService,
    private readonly shiftWeekService: ShiftWeekService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly preferredWeekService: PreferredWeekService,
  ) {}

  @Query(() => WorkingWeek)
  @Secured()
  async workingWeekGetFromCurrentWeek(
    @Args({ name: 'skipWeeks', type: () => Int }) skipWeeks: number,
    @CurrentUser() userId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const userWorkingBranches = await user.dbWorkingBranches;
    const branchIds = userWorkingBranches.map(b => b.id);
    const startDay = getNextMonday(skipWeeks);

    const preferredWeek = await this.preferredWeekService.findByStartDayAndUserId(
      startDay,
      user.id,
    );

    const relevantWeeks = await this.shiftWeekService.findByBranchIdsAndStartDay(
      branchIds,
      startDay,
    );

    const dayStart = +(
      await this.globalSettingsService.findByName(GlobalSettings.DAY_START)
    ).value;
    if (!dayStart) throw new InternalServerErrorException();

    const weekHours = new Map<
      { id: number; day: string; branchName: string; shiftRoleType: string },
      { hour: number; halfHour: boolean }[]
    >();
    const publishedBranches = [];
    const totalBranchCount = branchIds.length;

    for (const relevantWeek of relevantWeeks) {
      if (relevantWeek.published) {
        publishedBranches.push((await relevantWeek.branch).name);
        const relevantDays = await relevantWeek.shiftDays;
        for (const day of dayList) {
          const relevantDay: ShiftDay = relevantDays.find(
            d => d.day === Day[day],
          );
          const relevantRoles = await relevantDay.shiftRoles;

          let mapKey = {
            id: relevantDay.id,
            day,
            branchName: (await relevantWeek.branch).name,
            shiftRoleType: '',
          };

          for (const relevantRole of relevantRoles) {
            mapKey = {
              ...mapKey,
              shiftRoleType: (await relevantRole.type).name,
            };
            const relevantHours = await relevantRole.shiftHours;

            for (const relevantHour of relevantHours) {
              if (
                (await relevantHour.dbWorker) &&
                (await relevantHour.dbWorker).id === userId
              ) {
                const weekHour = weekHours.get(mapKey);
                const relevantHourRole = await relevantHour.shiftRole;

                const firstHour = getShiftRoleFirstHour({
                  shiftHours: await relevantHourRole.shiftHours,
                  dayStart,
                });
                const halfHour =
                  relevantHourRole.halfHour &&
                  firstHour === relevantHour.startHour;

                if (weekHour) {
                  weekHours.set(mapKey, [
                    ...weekHour,
                    { hour: relevantHour.startHour, halfHour },
                  ]);
                } else {
                  weekHours.set(mapKey, [
                    { hour: relevantHour.startHour, halfHour },
                  ]);
                }
              }
            }
          }
        }
      }
    }

    const workingWeek = new WorkingWeek();
    workingWeek.publishedBranches = publishedBranches;
    workingWeek.totalBranchCount = totalBranchCount;

    for (const day of dayList) {
      const workingDay = new WorkingDay();
      workingDay.workingIntervals = [];
      workingWeek[day] = workingDay;
    }

    for (const weekDay of weekHours.keys()) {
      const dayHours = weekHours.get(weekDay);

      const dayIntervals: {
        from: number;
        to: number;
        branchName: string;
        shiftRoleType: string;
        halfHour: boolean;
      }[] = [];
      let currentInterval: {
        from: number;
        to: number;
        halfHour: boolean;
        branchName: string;
        shiftRoleType: string;
      };

      for (let h = dayStart; h !== dayStart - 1; h++) {
        if (h > 23) {
          h = 0;
          if (dayStart === 0) {
            break;
          }
        }
        if (dayHours.some(dh => dh.hour === h)) {
          if (!currentInterval) {
            currentInterval = {
              from: h,
              to: h,
              halfHour: dayHours.find(dh => dh.hour === h).halfHour,
              branchName: weekDay.branchName,
              shiftRoleType: weekDay.shiftRoleType,
            };
          } else if (currentInterval.branchName === weekDay.branchName) {
            currentInterval = { ...currentInterval, to: h };
          } else {
            dayIntervals.push(currentInterval);
            currentInterval = {
              from: h,
              to: h,
              halfHour: false,
              branchName: weekDay.branchName,
              shiftRoleType: weekDay.shiftRoleType,
            };
          }
        } else if (currentInterval) {
          dayIntervals.push(currentInterval);
          currentInterval = undefined;
        }
      }
      if (currentInterval) dayIntervals.push(currentInterval);

      const workingDay: WorkingDay = workingWeek[weekDay.day];

      workingDay.workingIntervals.push(...dayIntervals);
    }
    workingWeek.preferredWeek = preferredWeek;

    return workingWeek;
  }
}

export default WorkingWeekResolver;
