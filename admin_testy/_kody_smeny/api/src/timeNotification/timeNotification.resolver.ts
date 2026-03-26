import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { BadRequestException } from '@nestjs/common';
import { Int } from 'type-graphql';

import Repeat from '../utils/repeat';

import TimeNotificationService from './timeNotification.service';
import TimeNotification from './timeNotification.entity';

@Resolver()
class TimeNotificationResolver {
  constructor(
    private readonly timeNotificationService: TimeNotificationService,
  ) {}

  @Query(() => [TimeNotification])
  timeNotificationFindAll(): Promise<TimeNotification[]> {
    return this.timeNotificationService.findAll();
  }

  @Query(() => TimeNotification)
  timeNotificationFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
  ): Promise<TimeNotification> {
    return this.timeNotificationService.findById(id);
  }

  @Mutation(() => TimeNotification)
  timeNotificationCreate(
    @Args('name') name: string,
    @Args('message') message: string,
    @Args({ name: 'repeat', type: () => Repeat }) repeat: Repeat,
    @Args({ name: 'date', type: () => Date, nullable: true }) date?: Date,
  ): Promise<TimeNotification> {
    if (repeat !== Repeat.never && !date) throw new BadRequestException();

    const timeNotification = new TimeNotification();
    timeNotification.name = name;
    timeNotification.message = message;
    timeNotification.date = date;
    timeNotification.repeat = repeat;

    return this.timeNotificationService.save(timeNotification);
  }

  @Mutation(() => TimeNotification)
  async timeNotificationUpdate(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('name')
    name: string,
    @Args('message') message: string,
    @Args({ name: 'repeat', type: () => Repeat }) repeat: Repeat,
    @Args({ name: 'date', type: () => Date, nullable: true }) date?: Date,
  ): Promise<TimeNotification> {
    if (repeat !== Repeat.never && !date) throw new BadRequestException();

    const timeNotification = await this.timeNotificationService.findById(id);
    if (!timeNotification) throw new BadRequestException();
    timeNotification.name = name;
    timeNotification.message = message;
    timeNotification.date = date;
    timeNotification.repeat = repeat;

    return this.timeNotificationService.save(timeNotification);
  }
}

export default TimeNotificationResolver;
