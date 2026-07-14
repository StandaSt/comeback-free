import { Parent, ResolveProperty, Resolver } from '@nestjs/graphql';

import User from '../user/user.entity';
import AuthService from '../auth/auth.service';
import CurrentUser from '../auth/currentUser.decorator';
import resources from '../config/api/resources';

import Evaluation from './evalution.entity';

@Resolver(Evaluation)
class EvaluationResolver {
  constructor(private readonly authService: AuthService) {}

  @ResolveProperty(() => User, { nullable: true })
  async evaluator(
    @Parent() parent: Evaluation,
    @CurrentUser() id: number,
  ): Promise<User> {
    if (
      await this.authService.hasResources(id, [resources.evaluation.history])
    ) {
      return parent.evaluater;
    }

    return null;
  }

  @ResolveProperty(() => Date, { nullable: true })
  async date(
    @Parent() parent: Evaluation,
    @CurrentUser() id: number,
  ): Promise<Date> {
    if (
      await this.authService.hasResources(id, [resources.evaluation.history])
    ) {
      return parent.date;
    }

    return null;
  }
}

export default EvaluationResolver;
