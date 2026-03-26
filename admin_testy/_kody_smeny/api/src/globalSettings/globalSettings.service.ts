import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import GlobalSettings from './globalSettings.entity';

@Injectable()
class GlobalSettingsService {
  constructor(
    @InjectRepository(GlobalSettings)
    private readonly globalSettingsRepository: Repository<GlobalSettings>,
  ) {}

  async save(globalSettings: GlobalSettings): Promise<GlobalSettings> {
    return this.globalSettingsRepository.save(globalSettings);
  }

  async findByName(name: string): Promise<GlobalSettings> {
    return this.globalSettingsRepository.findOne({ name });
  }
}

export default GlobalSettingsService;
