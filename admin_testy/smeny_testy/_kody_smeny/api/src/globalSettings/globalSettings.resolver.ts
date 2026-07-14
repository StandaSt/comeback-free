import { InternalServerErrorException } from '@nestjs/common';
import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';

import Secured from 'auth/secured.guard';
import resources from 'config/api/resources';

import GlobalSettings from './globalSettings.entity';
import GlobalSettingsService from './globalSettings.service';

@Resolver()
class GlobalSettingsResolver {
  constructor(private readonly globalSettingsService: GlobalSettingsService) {}

  @Query(() => GlobalSettings)
  @Secured()
  async globalSettingsFindDayStart() {
    return this.globalSettingsService.findByName(GlobalSettings.DAY_START);
  }

  @Query(() => GlobalSettings)
  @Secured()
  async globalSettingsFindPreferredWeeksAhead() {
    return this.globalSettingsService.findByName(
      GlobalSettings.PREFERRED_WEEKS_AHEAD,
    );
  }

  @Query(() => GlobalSettings)
  @Secured()
  async globalSettingsFindPreferredDeadline() {
    return this.globalSettingsService.findByName(
      GlobalSettings.PREFERRED_DEADLINE,
    );
  }

  @Query(() => GlobalSettings)
  @Secured()
  async globalSettingsFindEvaluationCooldown(): Promise<GlobalSettings> {
    return this.globalSettingsService.findByName(
      GlobalSettings.EVALUATION_COOLDOWN,
    );
  }

  @Query(() => GlobalSettings)
  @Secured()
  async globalSettingsFindEvaluationTTL(): Promise<GlobalSettings> {
    return this.globalSettingsService.findByName(GlobalSettings.EVALUATION_TTL);
  }

  @Query(() => GlobalSettings)
  @Secured()
  globalSettingsDeadlineNotification(): Promise<GlobalSettings> {
    return this.globalSettingsService.findByName(
      GlobalSettings.DEADLINE_NOTIFICATION,
    );
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangeDayStart(
    @Args({ name: 'dayStart', type: () => Int }) dayStart: number,
  ) {
    const dayStartSettings = await this.globalSettingsService.findByName(
      GlobalSettings.DAY_START,
    );
    if (!dayStartSettings) throw new InternalServerErrorException();

    dayStartSettings.value = dayStart.toString();

    return this.globalSettingsService.save(dayStartSettings);
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangePreferredWeeksAhead(
    @Args({ name: 'preferredWeeksAhead', type: () => Int })
    preferredWeeksAhead: number,
  ) {
    const preferredWeeksAheadSettings = await this.globalSettingsService.findByName(
      GlobalSettings.PREFERRED_WEEKS_AHEAD,
    );
    if (!preferredWeeksAheadSettings) throw new InternalServerErrorException();

    preferredWeeksAheadSettings.value = preferredWeeksAhead.toString();

    return this.globalSettingsService.save(preferredWeeksAheadSettings);
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangePreferredDeadline(
    @Args('preferredDeadline') preferredDeadline: Date,
  ) {
    const preferredDeadlineSettings = await this.globalSettingsService.findByName(
      GlobalSettings.PREFERRED_DEADLINE,
    );
    if (!preferredDeadlineSettings) throw new InternalServerErrorException();

    const trimmed = new Date(preferredDeadline);
    trimmed.setMinutes(0);
    trimmed.setSeconds(0);
    trimmed.setMilliseconds(0);
    preferredDeadlineSettings.value = trimmed.toISOString();

    return this.globalSettingsService.save(preferredDeadlineSettings);
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangeEvaluationCooldown(
    @Args({ name: 'cooldown', type: () => Int }) cooldown: number,
  ): Promise<GlobalSettings> {
    const evaluationCooldown = await this.globalSettingsService.findByName(
      GlobalSettings.EVALUATION_COOLDOWN,
    );
    if (!evaluationCooldown) throw new InternalServerErrorException();

    evaluationCooldown.value = cooldown.toString();

    return this.globalSettingsService.save(evaluationCooldown);
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangeEvaluationTTL(
    @Args({ name: 'ttl', type: () => Int }) cooldown: number,
  ): Promise<GlobalSettings> {
    const evaluationTTL = await this.globalSettingsService.findByName(
      GlobalSettings.EVALUATION_TTL,
    );
    if (!evaluationTTL) throw new InternalServerErrorException();

    evaluationTTL.value = cooldown.toString();

    return this.globalSettingsService.save(evaluationTTL);
  }

  @Mutation(() => GlobalSettings)
  @Secured(resources.globalSettings.edit)
  async globalSettingsChangeDeadlineNotification(
    @Args({ name: 'deadlineNotification', type: () => Int })
    notification: number,
  ): Promise<GlobalSettings> {
    const deadlineNotification = await this.globalSettingsService.findByName(
      GlobalSettings.DEADLINE_NOTIFICATION,
    );
    if (!deadlineNotification) throw new InternalServerErrorException();

    deadlineNotification.value = notification.toString();

    return this.globalSettingsService.save(deadlineNotification);
  }
}

export default GlobalSettingsResolver;
