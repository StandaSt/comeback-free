import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';

import GlobalSettings from './globalSettings.entity';
import GlobalSettingsResolver from './globalSettings.resolver';
import GlobalSettingsService from './globalSettings.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([GlobalSettings]),
    forwardRef(() => AuthModule),
  ],
  providers: [GlobalSettingsResolver, GlobalSettingsService],
  exports: [GlobalSettingsService],
})
class GlobalSettingsModule {}

export default GlobalSettingsModule;
