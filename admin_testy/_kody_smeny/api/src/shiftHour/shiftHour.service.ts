import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ShiftHour from './shiftHour.entity';

@Injectable()
class ShiftHourService {
  constructor(
    @InjectRepository(ShiftHour)
    private readonly shiftHourRepository: Repository<ShiftHour>,
  ) {}

  async save(shiftHour: ShiftHour): Promise<ShiftHour> {
    return this.shiftHourRepository.save(shiftHour);
  }

  async findById(id: number): Promise<ShiftHour> {
    return this.shiftHourRepository.findOne(id);
  }

  async remove(shiftHours: ShiftHour[]): Promise<ShiftHour[]> {
    return this.shiftHourRepository.remove(shiftHours);
  }

  getQueryBuilder(alias: string) {
    return this.shiftHourRepository.createQueryBuilder(alias);
  }
}

export default ShiftHourService;
