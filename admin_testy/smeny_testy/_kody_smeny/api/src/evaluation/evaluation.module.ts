import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';

import Evaluation from './evalution.entity';
import EvaluationService from './evaluation.service';
import EvaluationResolver from './evaluation.resolver';

@Module({
  imports: [
    TypeOrmModule.forFeature([Evaluation]),
    forwardRef(() => AuthModule),
  ],
  providers: [EvaluationResolver, EvaluationService],
  exports: [EvaluationService],
})
class EvaluationModule {}

export default EvaluationModule;
