import { SetMetadata } from '@nestjs/common';

const ResourceDecorator = (resources: string[]) =>
  SetMetadata('resources', resources);

export default ResourceDecorator;
