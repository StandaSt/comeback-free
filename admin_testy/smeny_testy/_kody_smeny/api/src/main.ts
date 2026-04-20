import { NestFactory } from '@nestjs/core';

import apiConfig from './config/api';
import AppModule from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  await app.listen(apiConfig.port);
}
bootstrap();
