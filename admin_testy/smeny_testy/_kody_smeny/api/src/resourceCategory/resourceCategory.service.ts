import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ResourceCategory from './resourceCategory.entity';

@Injectable()
class ResourceCategoryService {
  constructor(
    @InjectRepository(ResourceCategory)
    private readonly resourceCategory: Repository<ResourceCategory>,
  ) {}

  async findAllAndOrderByLabel() {
    return this.resourceCategory.find({ order: { label: 'ASC' } });
  }

  async findById(id: number) {
    return this.resourceCategory.findOne(id);
  }
}

export default ResourceCategoryService;
