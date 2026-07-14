import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import TimeNotification from './timeNotification.entity';
import TimeNotificationService from './timeNotification.service';
import TimeNotificationResolver from './timeNotification.resolver';

@Module({
  imports: [TypeOrmModule.forFeature([TimeNotification])],
  providers: [TimeNotificationService, TimeNotificationResolver],
})
class TimeNotificationModule {}

export default TimeNotificationModule;
