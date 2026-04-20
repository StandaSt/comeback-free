import { Module } from '@nestjs/common';
import { GraphQLModule } from '@nestjs/graphql';
import { TypeOrmModule } from '@nestjs/typeorm';

import RelevantUserModule from 'relevantUser/relevantUser.module';
import AuthModule from 'auth/auth.module';
import BranchModule from 'branch/branch.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import PreferredDayModule from 'preferredDay/preferredDay.module';
import PreferredHourModule from 'preferredHour/preferredHour.module';
import PreferredWeekModule from 'preferredWeek/preferredWeek.module';
import ResourceModule from 'resource/resource.module';
import ResourceCategoryModule from 'resourceCategory/resourceCategory.module';
import RoleModule from 'role/role.module';
import ShiftDayModule from 'shiftDay/shiftDay.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftRoleTypeModule from 'shiftRoleType/shiftRoleType.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';
import ShiftWeekTemplateModule from 'shiftWeekTemplate/shiftWeekTemplate.module';
import UserModule from 'user/user.module';
import WorkingWeekModule from 'workingWeek/workingWeek.module';
import ActionHistoryModule from 'actionHistory/actionHistory.module';
import EvaluationModule from 'evaluation/evaluation.module';
import NotificationModule from 'notification/notification.module';
import EventNotificationModule from 'eventNotification/eventNotification.module';
import TimeNotificationModule from 'timeNotification/timeNotification.module';

import TimeNotificationReceiverGroupModule from './timeNotificationReceiverGroup/timeNotificationReceiverGroup.module';
import TimeNotificationReceiverModule from './timeNotificationReceiver/timeNotificationReceiver.module';

@Module({
  imports: [
    AuthModule,
    UserModule,
    RoleModule,
    ResourceModule,
    ResourceCategoryModule,
    BranchModule,
    ShiftWeekModule,
    ShiftDayModule,
    ShiftRoleModule,
    ShiftRoleTypeModule,
    PreferredWeekModule,
    PreferredDayModule,
    PreferredHourModule,
    GlobalSettingsModule,
    WorkingWeekModule,
    ShiftWeekTemplateModule,
    RelevantUserModule,
    ActionHistoryModule,
    EvaluationModule,
    NotificationModule,
    EventNotificationModule,
    TimeNotificationModule,
    TimeNotificationReceiverGroupModule,
    TimeNotificationReceiverModule,
    GraphQLModule.forRoot({
      autoSchemaFile: 'schema.graphql',
      context: ({ req }) => ({ req }),
    }),
    TypeOrmModule.forRoot(),
  ],
})
export default class AppModule {}
