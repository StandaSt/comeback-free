import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import EventNotification from './eventNotification.entity';

@Injectable()
class EventNotificationService {
  constructor(
    @InjectRepository(EventNotification)
    private readonly eventNotificationRepository: Repository<EventNotification>,
  ) {}

  save(eventNotification: EventNotification): Promise<EventNotification> {
    return this.eventNotificationRepository.save(eventNotification);
  }

  findAll(): Promise<EventNotification[]> {
    return this.eventNotificationRepository.find();
  }

  findByEventName(eventName: string): Promise<EventNotification> {
    return this.eventNotificationRepository.findOne({ eventName });
  }

  findById(id: number): Promise<EventNotification> {
    return this.eventNotificationRepository.findOne(id);
  }
}

export default EventNotificationService;
