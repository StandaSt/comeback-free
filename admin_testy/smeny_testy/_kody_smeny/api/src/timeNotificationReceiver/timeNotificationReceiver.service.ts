import { BadRequestException, Injectable } from '@nestjs/common';
import { Repository } from 'typeorm';
import { InjectRepository } from '@nestjs/typeorm';

import RoleService from '../role/role.service';
import ResourceService from '../resource/resource.service';

import TimeNotificationReceiver from './timeNotificationReceiver.entity';
import TimeNotificationReceiverArg from './timeNotificationReceiver.arg';

@Injectable()
class TimeNotificationReceiverService {
  constructor(
    @InjectRepository(TimeNotificationReceiver)
    private readonly timeNotificationReceiverRepository: Repository<
      TimeNotificationReceiver
    >,
    private readonly roleService: RoleService,
    private readonly resourceService: ResourceService,
  ) {}

  save(
    timeNotificationReceiver: TimeNotificationReceiver,
  ): Promise<TimeNotificationReceiver> {
    return this.timeNotificationReceiverRepository.save(
      timeNotificationReceiver,
    );
  }

  findById(id: number): Promise<TimeNotificationReceiver | undefined> {
    return this.timeNotificationReceiverRepository.findOne(id);
  }

  async edit(
    timeNotificationReceiver: TimeNotificationReceiver,
    timeNotificationReceiverArg: TimeNotificationReceiverArg,
  ): Promise<TimeNotificationReceiver> {
    timeNotificationReceiver.role = null;
    timeNotificationReceiver.resource = null;

    if (timeNotificationReceiverArg.resourceId) {
      const resource = await this.resourceService.findById(
        timeNotificationReceiverArg.resourceId,
      );
      if (!resource) throw new BadRequestException();
      timeNotificationReceiver.resource = Promise.resolve(resource);
    } else if (timeNotificationReceiverArg.roleId) {
      const role = await this.roleService.findById(
        timeNotificationReceiverArg.roleId,
      );
      if (!role) throw new BadRequestException();
      timeNotificationReceiver.role = Promise.resolve(role);
    } else {
      throw new BadRequestException();
    }

    return timeNotificationReceiver;
  }
}

export default TimeNotificationReceiverService;
