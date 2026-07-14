import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import ResourceModule from 'resource/resource.module';
import RoleModule from 'role/role.module';

import TimeNotificationReceiver from './timeNotificationReceiver.entity';
import TimeNotificationReceiverService from './timeNotificationReceiver.service';
import TimeNotificationReceiverResolver from './timeNotificationReceiver.resolver';

@Module({
  imports: [
    TypeOrmModule.forFeature([TimeNotificationReceiver]),
    ResourceModule,
    RoleModule,
  ],
  providers: [
    TimeNotificationReceiverService,
    TimeNotificationReceiverResolver,
  ],
  exports: [TimeNotificationReceiverService],
})
class TimeNotificationReceiverModule {}
export default TimeNotificationReceiverModule;
