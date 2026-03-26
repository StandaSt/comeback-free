import {
  Resolver,
  Query,
  Args,
  Mutation,
  ResolveProperty,
  Parent,
} from '@nestjs/graphql';
import { Int } from 'type-graphql';
import { BadRequestException } from '@nestjs/common';

import Secured from '../auth/secured.guard';
import resources from '../config/api/resources';

import EventNotificationService from './eventNotification.service';
import EventNotification, {
  EventNotificationVariable,
} from './eventNotification.entity';

@Resolver(() => EventNotification)
class EventNotificationResolver {
  constructor(
    private readonly eventNotificationService: EventNotificationService,
  ) {}

  @Secured(resources.notifications.eventSee)
  @Query(() => [EventNotification])
  eventNotificationFindAll(): Promise<EventNotification[]> {
    return this.eventNotificationService.findAll();
  }

  @Secured(resources.notifications.eventSee)
  @Query(() => EventNotification)
  eventNotificationFindById(
    @Args({ name: 'id', type: () => Int }) id: number,
  ): Promise<EventNotification> {
    return this.eventNotificationService.findById(id);
  }

  @Secured(resources.notifications.eventEdit)
  @Mutation(() => EventNotification)
  async eventNotificationEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('message') message: string,
  ) {
    const eventNotifications = await this.eventNotificationService.findById(id);

    if (!eventNotifications) throw new BadRequestException();

    eventNotifications.message = message;

    return this.eventNotificationService.save(eventNotifications);
  }

  @Secured(resources.notifications.eventSee)
  @ResolveProperty(() => [EventNotificationVariable])
  variables(@Parent() parent: EventNotification): EventNotificationVariable[] {
    const variables: { value: string; description: string }[] = JSON.parse(
      parent.variables,
    );

    return variables.map(v => {
      const eventNotificationVariable = new EventNotificationVariable();
      eventNotificationVariable.value = v.value;
      eventNotificationVariable.description = v.description;

      return eventNotificationVariable;
    });
  }
}

export default EventNotificationResolver;
