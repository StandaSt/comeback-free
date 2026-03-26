import { BadRequestException } from '@nestjs/common';
import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';
import dateFormat from 'dateformat';

import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';
import GlobalSettings from 'globalSettings/globalSettings.entity';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import PreferredDayService from 'preferredDay/preferredDay.service';
import UserService from 'user/user.service';
import getNextMonday from 'utils/getNextMonday';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';

import PreferredWeek from './preferredWeek.entity';
import PreferredWeekService from './preferredWeek.service';

@Resolver()
class PreferredWeekResolver {
  constructor(
    private readonly preferredWeekService: PreferredWeekService,
    private readonly userService: UserService,
    private readonly preferredDayService: PreferredDayService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly shiftWeekService: ShiftWeekService,
  ) {}

  @Query(() => PreferredWeek)
  @Secured(resources.preferredWeeks.see)
  async preferredWeekFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
    @CurrentUser() userId: number,
  ) {
    const preferredWeek = await this.preferredWeekService.findById(id);
    if ((await preferredWeek.user).id !== userId) {
      throw new BadRequestException();
    }

    return preferredWeek;
  }

  @Query(() => [PreferredWeek])
  @Secured(resources.preferredWeeks.see)
  async preferredWeekGetRelevant(@CurrentUser() userId: number) {
    const formatDate = (date: Date): string => {
      return dateFormat(new Date(date), 'dd.mm.yyyy');
    };
    const preferredWeekAhead = +(
      await this.globalSettingsService.findByName(
        GlobalSettings.PREFERRED_WEEKS_AHEAD,
      )
    ).value;

    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const preferredWeeks = await user.dbPreferredWeeks;

    const relevantWeeks = [];

    for (let i = 0; i < preferredWeekAhead; i++) {
      const futureMonday = getNextMonday(i);

      let futureWeek = preferredWeeks.find(
        w => formatDate(w.startDay) === formatDate(futureMonday),
      );
      if (!futureWeek) {
        futureWeek = await this.preferredWeekService.createNew(
          futureMonday,
          user,
        );
      }

      relevantWeeks.push(futureWeek);
    }

    return relevantWeeks;
  }

  @Query(() => [PreferredWeek])
  @Secured(resources.enteredPreferredWeeks.see)
  async preferredWeekFindAllInWeek(
    @Args({ name: 'skipWeeks', type: () => Int }) skipWeek: number,
  ) {
    const futureMonday = getNextMonday(skipWeek);

    return this.preferredWeekService.findAllUsersByStartDay(futureMonday);
  }

  @Mutation(() => PreferredWeek)
  @Secured(resources.preferredWeeks.see)
  async preferredWeekConfirm(
    @Args({ name: 'weekId', type: () => Int }) weekId: number,
    @CurrentUser() userId: number,
  ): Promise<PreferredWeek> {
    const week = await this.preferredWeekService.findById(weekId);
    if (!weekId) throw new BadRequestException();

    const user = await week.user;
    if (user.id !== userId) throw new BadRequestException();

    const branches = await user.dbWorkingBranches;

    const shiftWeeks = await this.shiftWeekService.findByBranchIdsAndStartDay(
      branches.map(b => b.id),
      week.startDay,
    );
    if (shiftWeeks.some(w => !w.published)) {
      throw new BadRequestException();
    }
    week.confirmed = true;

    return this.preferredWeekService.save(week);
  }
}

export default PreferredWeekResolver;
