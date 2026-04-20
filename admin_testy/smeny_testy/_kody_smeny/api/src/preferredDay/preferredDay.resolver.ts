import { BadRequestException } from '@nestjs/common';
import { Args, Mutation, Resolver } from '@nestjs/graphql';

import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import historyName from 'config/api/history';
import resources from 'config/api/resources';
import PreferredHour from 'preferredHour/preferredHour.entity';
import PreferredHourService from 'preferredHour/preferredHour.service';
import DayHour from 'preferredWeek/args/dayHour.arg';
import PreferredWeekService from 'preferredWeek/preferredWeek.service';
import ActionHistoryService from 'actionHistory/actionHistory.service';

import PreferredDay from './preferredDay.entity';
import PreferredDayService from './preferredDay.service';

@Resolver()
class PreferredDayResolver {
  constructor(
    private readonly preferredDayService: PreferredDayService,
    private readonly preferredHourService: PreferredHourService,
    private readonly preferredWeekService: PreferredWeekService,
    private readonly actionHistoryService: ActionHistoryService,
  ) {}

  @Mutation(() => [PreferredDay])
  @Secured(resources.preferredWeeks.see)
  async preferredDayChangeHours(
    @Args({ name: 'dayHours', type: () => [DayHour] }) dayHours: DayHour[],
    @CurrentUser() userId: number,
  ) {
    const editedDays = [];
    for (const dayHour of dayHours) {
      const preferredDay = await this.preferredDayService.findById(
        dayHour.dayId,
      );
      if (!preferredDay) throw new BadRequestException();

      const preferredWeek = await preferredDay.preferredWeek;

      if ((await preferredWeek.user).id !== userId)
        throw new BadRequestException();

      preferredWeek.lastEditTime = new Date(Date.now());
      this.preferredWeekService.save(preferredWeek);

      const preferredDayHours = await preferredDay.preferredHours;
      const newHours = [];
      for (const h of dayHour.hours) {
        let hour = preferredDayHours.find(ph => ph.startHour === h);
        if (!hour) {
          hour = new PreferredHour();
          hour.startHour = h;
          hour = await this.preferredHourService.save(hour);
        }
        hour.visible = true;
        newHours.push(hour);
      }

      this.preferredHourService.saveMultiple(newHours);
      preferredDay.preferredHours = Promise.resolve(newHours);
      editedDays.push(await this.preferredDayService.save(preferredDay));
    }

    this.actionHistoryService.addRecord({
      name: historyName.preferredDay.changeHours,
      additionalData: dayHours,
      userId,
    });

    return editedDays;
  }
}

export default PreferredDayResolver;
