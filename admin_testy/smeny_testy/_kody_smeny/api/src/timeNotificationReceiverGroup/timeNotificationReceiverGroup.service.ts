import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import TimeNotificationReceiverGroup from './timeNotificationReceiverGroup.entity';

@Injectable()
class TimeNotificationReceiverGroupService {
  constructor(
    @InjectRepository(TimeNotificationReceiverGroup)
    private readonly timeNotificationReceiverGroupRepository: Repository<
      TimeNotificationReceiverGroup
    >,
  ) {}

  save(
    timeNotificationReceiverGroup: TimeNotificationReceiverGroup,
  ): Promise<TimeNotificationReceiverGroup> {
    return this.timeNotificationReceiverGroupRepository.save(
      timeNotificationReceiverGroup,
    );
  }

  findById(id: number): Promise<TimeNotificationReceiverGroup | undefined> {
    return this.timeNotificationReceiverGroupRepository.findOne(id);
  }
}

export default TimeNotificationReceiverGroupService;
