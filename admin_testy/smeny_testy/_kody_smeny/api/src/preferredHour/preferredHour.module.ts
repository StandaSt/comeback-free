import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';

import PreferredHour from './preferredHour.entity';
import PreferredHourResolver from './preferredHour.resolver';
import PreferredHourService from './preferredHour.service';

@Module({
  imports: [TypeOrmModule.forFeature([PreferredHour])],
  providers: [PreferredHourResolver, PreferredHourService],
  exports: [PreferredHourService],
})
class PreferredHourModule {}

export default PreferredHourModule;
