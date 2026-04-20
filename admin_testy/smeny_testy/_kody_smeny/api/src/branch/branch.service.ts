import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import Branch from './branch.entity';

@Injectable()
class BranchService {
  constructor(
    @InjectRepository(Branch)
    private readonly branchRepository: Repository<Branch>,
  ) {}

  async save(branch: Branch) {
    return this.branchRepository.save(branch);
  }

  async findAll() {
    return this.branchRepository.find();
  }

  async findActive() {
    return this.branchRepository.find({ active: true });
  }

  async findById(id: number) {
    return this.branchRepository.findOne(id);
  }
}

export default BranchService;
