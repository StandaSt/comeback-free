import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import User from 'user/user.entity';

import Day from '../utils/day';

import PreferredDay from './preferredDay.entity';

@Injectable()
class PreferredDayService {
  constructor(
    @InjectRepository(PreferredDay)
    private readonly preferredDayRepository: Repository<PreferredDay>,
  ) {}

  async save(preferredDay: PreferredDay): Promise<PreferredDay> {
    return this.preferredDayRepository.save(preferredDay);
  }

  async findById(id: number): Promise<PreferredDay> {
    return this.preferredDayRepository.findOne(id);
  }

  remove(days: PreferredDay[]): Promise<PreferredDay[]> {
    return this.preferredDayRepository.remove(days);
  }

  async findByStartDay(startDay: Date, day: Day, user: User) {
    return this.preferredDayRepository
      .createQueryBuilder('preferredDay')
      .innerJoin('preferredDay.preferredWeek', 'preferredWeek')
      .innerJoin('preferredWeek.user', 'user')
      .where('preferredWeek.startDay = :startDay', { startDay })
      .andWhere('preferredDay.day = :day', { day })
      .andWhere('user.id = :id', { id: user.id })
      .getOne();
  }
}

export default PreferredDayService;
