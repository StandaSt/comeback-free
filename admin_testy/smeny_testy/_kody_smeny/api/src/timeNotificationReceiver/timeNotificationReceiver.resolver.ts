import { Args, Mutation, Resolver, Query } from '@nestjs/graphql';
import { Int } from 'type-graphql';
import { BadRequestException } from '@nestjs/common';

import TimeNotificationReceiverService from './timeNotificationReceiver.service';
import TimeNotificationReceiver from './timeNotificationReceiver.entity';
import TimeNotificationReceiverArg from './timeNotificationReceiver.arg';

@Resolver()
class TimeNotificationReceiverResolver {
  constructor(
    private readonly timeNotificationReceiverService: TimeNotificationReceiverService,
  ) {}

  @Query(() => TimeNotificationReceiver)
  timeNotificationReceiverFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
  ): Promise<TimeNotificationReceiver> {
    return this.timeNotificationReceiverService.findById(id);
  }

  @Mutation(() => TimeNotificationReceiver)
  async timeNotificationReceiverEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args({
      name: 'timeNotificationReceiver',
      type: () => TimeNotificationReceiverArg,
    })
    timeNotificationReceiverArgs: TimeNotificationReceiverArg,
  ): Promise<TimeNotificationReceiver> {
    const timeNotificationReceiver = await this.timeNotificationReceiverService.findById(
      id,
    );
    if (!timeNotificationReceiver) throw new BadRequestException();

    return this.timeNotificationReceiverService.save(
      await this.timeNotificationReceiverService.edit(
        timeNotificationReceiver,
        timeNotificationReceiverArgs,
      ),
    );
  }
}

export default TimeNotificationReceiverResolver;
