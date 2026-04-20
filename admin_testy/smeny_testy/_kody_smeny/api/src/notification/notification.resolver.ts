import { Args, Mutation, Query, Resolver } from '@nestjs/graphql';
import { BadRequestException } from '@nestjs/common';

import Secured from 'auth/secured.guard';

import UserService from '../user/user.service';
import CurrentUser from '../auth/currentUser.decorator';

import NotificationService from './notification.service';
import Notification from './notification.entity';

@Resolver()
class NotificationResolver {
  constructor(
    private readonly notificationService: NotificationService,
    private readonly userService: UserService,
  ) {}

  @Mutation(() => Boolean)
  @Secured()
  async notificationSaveSubscription(
    @Args('subscription') subscription: string,
    @CurrentUser() userId: number,
  ): Promise<boolean> {
    let parsed;
    try {
      parsed = JSON.parse(subscription);
    } catch (e) {
      throw new BadRequestException(e);
    }
    const stringified = JSON.stringify(parsed);
    const existing = await this.notificationService.findBySubscription(
      stringified,
    );
    const user = await this.userService.findById(userId);
    if (!existing) {
      const notification = new Notification();
      notification.subscription = stringified;
      notification.user = Promise.resolve(user);
      await this.notificationService.save(notification);
    } else if ((await existing.user).id !== userId) {
      existing.user = Promise.resolve(user);
      await this.notificationService.save(existing);
    }

    return true;
  }
}

export default NotificationResolver;
