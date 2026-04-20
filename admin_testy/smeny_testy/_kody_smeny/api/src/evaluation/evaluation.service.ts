import { Injectable } from '@nestjs/common';
import { Repository } from 'typeorm';
import { InjectRepository } from '@nestjs/typeorm';
import { annotateWithChildrenErrors } from 'graphql-tools/dist/stitching/errors';

import Evaluation from './evalution.entity';

@Injectable()
class EvaluationService {
  constructor(
    @InjectRepository(Evaluation)
    private readonly evaluationRepository: Repository<Evaluation>,
  ) {}

  save(evaluation: Evaluation): Promise<Evaluation> {
    return this.evaluationRepository.save(evaluation);
  }

  findById(id: number): Promise<Evaluation> {
    return this.evaluationRepository.findOne(id);
  }

  findAfterDate(date: Date, userId: number): Promise<Evaluation[]> {
    return this.evaluationRepository
      .createQueryBuilder('evaluation')
      .where('evaluation.date > :date', { date })
      .andWhere('evaluation.userId = :userId', { userId })
      .getMany();
  }

  async afterCooldown(
    date: Date,
    userId: number,
    evaluatorId: number,
  ): Promise<boolean> {
    return !(
      (await this.evaluationRepository
        .createQueryBuilder('evaluation')
        .where('evaluation.date > :date', { date })
        .andWhere('evaluation.userId = :userId', { userId })
        .andWhere('evaluation.evaluaterId = :evaluatorId', { evaluatorId })
        .getCount()) > 0
    );
  }
}

export default EvaluationService;
