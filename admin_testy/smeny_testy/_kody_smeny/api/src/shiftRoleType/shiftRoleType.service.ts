import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ShiftRoleType from './shiftRoleType.entity';

@Injectable()
class ShiftRoleTypeService {
  constructor(
    @InjectRepository(ShiftRoleType)
    private readonly shiftRoleTypeRepository: Repository<ShiftRoleType>,
  ) {}

  async save(shiftRoleType: ShiftRoleType): Promise<ShiftRoleType> {
    return this.shiftRoleTypeRepository.save(shiftRoleType);
  }

  async findAll(): Promise<ShiftRoleType[]> {
    return this.shiftRoleTypeRepository.find();
  }

  async findAllActive(): Promise<ShiftRoleType[]> {
    return this.shiftRoleTypeRepository.find({ active: true });
  }

  async findById(id: number): Promise<ShiftRoleType> {
    return this.shiftRoleTypeRepository.findOne(id);
  }

  async findRegistrationDefaults() {
    return this.shiftRoleTypeRepository.find({
      registrationDefault: true,
      active: true,
    });
  }
}

export default ShiftRoleTypeService;
