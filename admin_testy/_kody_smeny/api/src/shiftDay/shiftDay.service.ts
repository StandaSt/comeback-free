import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ShiftDay from './shiftDay.entity';

@Injectable()
class ShiftDayService {
  constructor(
    @InjectRepository(ShiftDay)
    private readonly shiftDayRepository: Repository<ShiftDay>,
  ) {}

  async save(shiftDay: ShiftDay): Promise<ShiftDay> {
    return this.shiftDayRepository.save(shiftDay);
  }

  async findById(id: number): Promise<ShiftDay> {
    return this.shiftDayRepository.findOne(id);
  }
}

export default ShiftDayService;
