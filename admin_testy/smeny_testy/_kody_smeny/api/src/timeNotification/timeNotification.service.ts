import { Repository } from 'typeorm';
import { InjectRepository } from '@nestjs/typeorm';
import { Injectable } from '@nestjs/common';

import TimeNotification from './timeNotification.entity';

@Injectable()
class TimeNotificationService {
  constructor(
    @InjectRepository(TimeNotification)
    private readonly timeNotificationRepository: Repository<TimeNotification>,
  ) {}

  save(timeNotification: TimeNotification): Promise<TimeNotification> {
    return this.timeNotificationRepository.save(timeNotification);
  }

  findAll(): Promise<TimeNotification[]> {
    return this.timeNotificationRepository.find();
  }

  findById(id: number): Promise<TimeNotification> {
    return this.timeNotificationRepository.findOne(id);
  }
}

export default TimeNotificationService;
