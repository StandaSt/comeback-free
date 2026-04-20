import { Args, Mutation, Resolver } from '@nestjs/graphql';
import { Int } from 'type-graphql';
import { BadRequestException } from '@nestjs/common';

import TimeNotificationReceiverArg from 'timeNotificationReceiver/timeNotificationReceiver.arg';
import TimeNotificationReceiverService from 'timeNotificationReceiver/timeNotificationReceiver.service';

import TimeNotificationReceiver from '../timeNotificationReceiver/timeNotificationReceiver.entity';

import TimeNotificationReceiverGroupService from './timeNotificationReceiverGroup.service';
import TimeNotificationReceiverGroup from './timeNotificationReceiverGroup.entity';

@Resolver()
class TimeNotificationReceiverGroupResolver {
  constructor(
    private readonly timeNotificationReceiverGroupService: TimeNotificationReceiverGroupService,
    private readonly timeNotificationReceiverService: TimeNotificationReceiverService,
  ) {}

  @Mutation(() => TimeNotificationReceiverGroup)
  async timeNotificationReceiverGroupAddTimeNotificationReceiver(
    @Args({ name: 'timeNotificationReceiverGroupId', type: () => Int })
    timeNotificationReceiverGroupId: number,
    @Args({
      name: 'timeNotificationReceiver',
      type: () => TimeNotificationReceiverArg,
    })
    timeNotificationReceiverArg: TimeNotificationReceiverArg,
  ): Promise<TimeNotificationReceiverGroup> {
    const timeNotificationReceiverGroup = await this.timeNotificationReceiverGroupService.findById(
      timeNotificationReceiverGroupId,
    );
    if (!timeNotificationReceiverGroup) throw new BadRequestException();

    let timeNotificationReceiver = new TimeNotificationReceiver();
    timeNotificationReceiver.receiverGroup = Promise.resolve(
      timeNotificationReceiverGroup,
    );

    timeNotificationReceiver = await this.timeNotificationReceiverService.edit(
      timeNotificationReceiver,
      timeNotificationReceiverArg,
    );

    timeNotificationReceiver = await this.timeNotificationReceiverService.save(
      timeNotificationReceiver,
    );

    const receivers = await timeNotificationReceiverGroup.receivers;
    timeNotificationReceiverGroup.receivers = Promise.resolve([
      ...receivers,
      timeNotificationReceiver,
    ]);

    return timeNotificationReceiverGroup;
  }
}

export default TimeNotificationReceiverGroupResolver;
