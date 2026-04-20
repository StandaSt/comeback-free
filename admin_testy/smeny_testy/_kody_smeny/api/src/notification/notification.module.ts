import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { SendGridModule } from '@ntegral/nestjs-sendgrid';

import AuthModule from 'auth/auth.module';
import UserModule from 'user/user.module';
import apiConfig from 'config/api';
import EventNotificationModule from 'eventNotification/eventNotification.module';

import Notification from './notification.entity';
import NotificationResolver from './notification.resolver';
import NotificationService from './notification.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([Notification]),
    SendGridModule.forRoot({ apiKey: apiConfig.sendgrid.apiKey }),
    forwardRef(() => AuthModule),
    forwardRef(() => UserModule),
    EventNotificationModule,
  ],
  providers: [NotificationResolver, NotificationService],
  exports: [NotificationService],
})
class NotificationModule {}

export default NotificationModule;
