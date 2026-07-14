import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import BranchModule from 'branch/branch.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';
import PreferredWeekModule from 'preferredWeek/preferredWeek.module';
import RoleModule from 'role/role.module';
import ShiftRoleModule from 'shiftRole/shiftRole.module';
import ShiftRoleTypeModule from 'shiftRoleType/shiftRoleType.module';
import ShiftWeekModule from 'shiftWeek/shiftWeek.module';
import UserPaginatorResolver from 'user/paginator/userPaginator.resolver';
import User from 'user/user.entity';
import UserResolver from 'user/user.resolver';
import UserService from 'user/user.service';
import ActionHistoryModule from 'actionHistory/actionHistory.module';
import EvaluationModule from 'evaluation/evaluation.module';
import NotificationModule from 'notification/notification.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([User]),
    forwardRef(() => AuthModule),
    RoleModule,
    forwardRef(() => BranchModule),
    ShiftRoleTypeModule,
    forwardRef(() => ShiftRoleModule),
    GlobalSettingsModule,
    ShiftWeekModule,
    forwardRef(() => ActionHistoryModule),
    forwardRef(() => PreferredWeekModule),
    EvaluationModule,
    NotificationModule,
  ],
  providers: [UserResolver, UserPaginatorResolver, UserService],
  exports: [UserService],
})
export default class UserModule {}
