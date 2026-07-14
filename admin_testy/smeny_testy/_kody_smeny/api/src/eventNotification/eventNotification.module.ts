import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from '../auth/auth.module';

import EventNotificationResolver from './eventNotification.resolver';
import EventNotificationService from './eventNotification.service';
import EventNotification from './eventNotification.entity';

@Module({
  imports: [
    forwardRef(() => AuthModule),
    TypeOrmModule.forFeature([EventNotification]),
  ],
  providers: [EventNotificationResolver, EventNotificationService],
  exports: [EventNotificationService],
})
class EventNotificationModule {}

export default EventNotificationModule;
