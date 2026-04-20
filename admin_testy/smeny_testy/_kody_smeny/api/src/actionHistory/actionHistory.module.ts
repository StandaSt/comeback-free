import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import UserModule from 'user/user.module';

import ActionHistoryPaginatorResolver from './paginator/actionHistoryPaginator.resolver';
import ActionHistory from './actionHistory.entity';
import ActionHistoryResolver from './actionHistory.resolver';
import ActionHistoryService from './actionHistory.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ActionHistory]),
    forwardRef(() => AuthModule),
    forwardRef(() => UserModule),
  ],
  providers: [
    ActionHistoryResolver,
    ActionHistoryPaginatorResolver,
    ActionHistoryService,
  ],
  exports: [ActionHistoryService],
})
class ActionHistoryModule {}

export default ActionHistoryModule;
