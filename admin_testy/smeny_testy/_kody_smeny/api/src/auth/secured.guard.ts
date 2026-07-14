import { UseGuards } from '@nestjs/common';

import GqlAuthGuard from 'auth/jwt.guard';
import ResourcesGuard from 'auth/resource.guard';

import ResourceDecorator from './resource.decorator';

const Secured = (...resources: string[]) => {
  const metadata = ResourceDecorator(resources);
  const resourceGuard = UseGuards(ResourcesGuard);
  const gqlGuard = UseGuards(GqlAuthGuard);

  return (target: any, key?: string, descriptor?: any) => {
    metadata(target, key, descriptor);
    gqlGuard(target, key, descriptor);
    resourceGuard(target, key, descriptor);
  };
};

export default Secured;
