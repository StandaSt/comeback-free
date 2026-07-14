import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import UserService from 'user/user.service';

import OrderByArg from '../paginator/orderBy.arg';
import User from '../user/user.entity';

import ActionHistoryFilterArg from './paginator/args/actionHistoryFilter';
import ActionHistory from './actionHistory.entity';

@Injectable()
class ActionHistoryService {
  constructor(
    @InjectRepository(ActionHistory)
    private readonly actionHistoryRepository: Repository<ActionHistory>,
    private readonly userService: UserService,
  ) {}

  save(actionHistory: ActionHistory): Promise<ActionHistory> {
    return this.actionHistoryRepository.save(actionHistory);
  }

  paginate(
    limit: number,
    offset: number,
    filter: ActionHistoryFilterArg,
    orderBy?: OrderByArg,
  ): Promise<ActionHistory[]> {
    const qb = this.actionHistoryRepository
      .createQueryBuilder('actionHistory')
      .innerJoinAndSelect('actionHistory.user', 'user')
      .where('user.surname like :surname', {
        surname: `%${filter.userSurname}%`,
      })
      .andWhere('user.name like :name', { name: `%${filter.userName}%` })
      .take(limit)
      .skip(offset);

    if (filter.date)
      qb.andWhere('DATE(actionHistory.date) = DATE(:date)', {
        date: new Date(filter.date),
      });

    if (
      !(
        (filter.name.length === 1 && filter.name[0] === '') ||
        filter.name.length === 0
      )
    ) {
      qb.where('actionHistory.name IN (:...name)', { name: filter.name });
    }
    qb.orderBy('actionHistory.date', 'DESC');
    if (orderBy) {
      qb.orderBy(orderBy.fieldName, orderBy.type);
    }

    return qb.getMany();
  }

  getTotalCount(filter: ActionHistoryFilterArg) {
    const qb = this.actionHistoryRepository
      .createQueryBuilder('actionHistory')
      .innerJoinAndSelect('actionHistory.user', 'user')
      .where('user.surname like :surname', {
        surname: `%${filter.userSurname}%`,
      })
      .andWhere('user.name like :name', { name: `%${filter.userName}%` });

    if (filter.date)
      qb.andWhere('DATE(actionHistory.date) = DATE(:date)', {
        date: new Date(filter.date),
      });

    if (
      !(
        (filter.name.length === 1 && filter.name[0] === '') ||
        filter.name.length === 0
      )
    ) {
      qb.andWhere('actionHistory.name IN (:...name)', { name: [filter.name] });
    }

    return qb.getCount();
  }

  findById(id: number): Promise<ActionHistory> {
    return this.actionHistoryRepository.findOne(id);
  }

  findByUser(user: User): Promise<ActionHistory[]> {
    return this.actionHistoryRepository.find({ where: { user } });
  }

  remove(history: ActionHistory[]): Promise<ActionHistory[]> {
    return this.actionHistoryRepository.remove(history);
  }

  async addRecord(record: {
    name: string;
    additionalData?: any;
    userId: number;
  }) {
    const actionHistory = new ActionHistory();
    actionHistory.name = record.name;
    actionHistory.additionalData = JSON.stringify(record.additionalData || {});
    actionHistory.date = new Date(Date.now());
    actionHistory.user = Promise.resolve(
      await this.userService.findById(record.userId),
    );

    return this.save(actionHistory);
  }
}

export default ActionHistoryService;
