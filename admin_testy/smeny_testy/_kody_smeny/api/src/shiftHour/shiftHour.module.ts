import { forwardRef, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import AuthModule from 'auth/auth.module';
import GlobalSettingsModule from 'globalSettings/globalSettings.module';

import ShiftHour from './shiftHour.entity';
import ShiftHourResolver from './shiftHour.resolver';
import ShiftHourService from './shiftHour.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([ShiftHour]),
    forwardRef(() => AuthModule),
    GlobalSettingsModule,
  ],
  providers: [ShiftHourResolver, ShiftHourService],
  exports: [ShiftHourService],
})
class ShiftHourModule {}

export default ShiftHourModule;
