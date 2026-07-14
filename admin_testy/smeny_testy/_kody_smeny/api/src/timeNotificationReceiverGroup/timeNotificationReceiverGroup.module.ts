import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import TimeNotificationReceiverModule from 'timeNotificationReceiver/timeNotificationReceiver.module';

import TimeNotificationReceiverGroup from './timeNotificationReceiverGroup.entity';
import TimeNotificationReceiverGroupResolver from './timeNotificationReceiverGroup.resolver';
import TimeNotificationReceiverGroupService from './timeNotificationReceiverGroup.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([TimeNotificationReceiverGroup]),
    TimeNotificationReceiverModule,
  ],
  providers: [
    TimeNotificationReceiverGroupService,
    TimeNotificationReceiverGroupResolver,
  ],
})
class TimeNotificationReceiverGroupModule {}

export default TimeNotificationReceiverGroupModule;
