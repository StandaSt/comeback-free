import { CanActivate, ExecutionContext, Injectable } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { GqlExecutionContext } from '@nestjs/graphql';

import AuthService from 'auth/auth.service';

@Injectable()
class ResourceGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly authService: AuthService,
  ) {}

  async canActivate(context: ExecutionContext) {
    const ctx = GqlExecutionContext.create(context);
    const { user } = ctx.getContext().req;

    const resources = this.reflector.get<string[]>(
      'resources',
      context.getHandler(),
    );

    return this.authService.hasResources(user, resources);
  }
}

export default ResourceGuard;
