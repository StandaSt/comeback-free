import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import ShiftHour from 'shiftHour/shiftHour.entity';
import User from 'user/user.entity';
import Day from 'utils/day';

import PreferredHour from './preferredHour.entity';

@Injectable()
class PreferredHourService {
  constructor(
    @InjectRepository(PreferredHour)
    private readonly preferredHourRepository: Repository<PreferredHour>,
  ) {}

  async save(preferredHour: PreferredHour): Promise<PreferredHour> {
    return this.preferredHourRepository.save(preferredHour);
  }

  async saveMultiple(
    preferredHours: PreferredHour[],
  ): Promise<PreferredHour[]> {
    return this.preferredHourRepository.save(preferredHours);
  }

  async getCorrespondingToShiftHour(
    shiftHour: ShiftHour,
    user: User,
    day: Day,
  ) {
    const { startDay } = await (await (await shiftHour.shiftRole).shiftDay)
      .shiftWeek;

    return this.preferredHourRepository
      .createQueryBuilder('preferredHour')
      .innerJoin('preferredHour.preferredDay', 'preferredDay')
      .innerJoin('preferredDay.preferredWeek', 'preferredWeek')
      .innerJoin('preferredWeek.user', 'user')
      .where('preferredWeek.startDay = :startDay', { startDay })
      .andWhere('preferredDay.day = :day', { day })
      .andWhere('preferredHour.startHour = :startHour', {
        startHour: shiftHour.startHour,
      })
      .andWhere('user.id = :userId', { userId: user.id })
      .getOne();
  }

  async delete(id: number) {
    return this.preferredHourRepository.delete(id);
  }

  remove(hours: PreferredHour[]): Promise<PreferredHour[]> {
    return this.preferredHourRepository.remove(hours);
  }

  getQueryBuilder(alias: string) {
    return this.preferredHourRepository.createQueryBuilder(alias);
  }
}

export default PreferredHourService;
