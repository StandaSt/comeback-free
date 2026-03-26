import {
  forwardRef,
  Inject,
  Injectable,
  InternalServerErrorException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Cron, CronExpression } from '@nestjs/schedule';
import dayjs from 'dayjs';

import resources from 'config/api/resources';
import PreferredDay from 'preferredDay/preferredDay.entity';
import PreferredDayService from 'preferredDay/preferredDay.service';
import User from 'user/user.entity';
import UserService from 'user/user.service';
import Day from 'utils/day';
import PreferredHourService from 'preferredHour/preferredHour.service';
import GlobalSettingsService from 'globalSettings/globalSettings.service';

import GlobalSettings from '../globalSettings/globalSettings.entity';
import NotificationService from '../notification/notification.service';
import notifications from '../config/api/notifications';
import routes from '../config/app/routes';

import PreferredWeek from './preferredWeek.entity';

@Injectable()
class PreferredWeekService {
  constructor(
    @InjectRepository(PreferredWeek)
    private readonly preferredWeekRepository: Repository<PreferredWeek>,
    private readonly preferredDayService: PreferredDayService,
    @Inject(forwardRef(() => UserService))
    private readonly userService: UserService,
    private readonly preferredHourService: PreferredHourService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly notificationService: NotificationService,
  ) {}

  async save(preferredWeek: PreferredWeek): Promise<PreferredWeek> {
    return this.preferredWeekRepository.save(preferredWeek);
  }

  async findById(
    id: number,
    options: { relations?: string[] } = { relations: [] },
  ): Promise<PreferredWeek> {
    return this.preferredWeekRepository.findOne(id, {
      relations: options.relations,
    });
  }

  findByStartDayAndUserId(
    startDay: Date,
    userId: number,
  ): Promise<PreferredWeek> {
    return this.preferredWeekRepository
      .createQueryBuilder('preferredWeek')
      .where('userId = :userId', { userId })
      .andWhere('preferredWeek.startDay = :startDay', { startDay })
      .getOne();
  }

  remove(weeks: PreferredWeek[]): Promise<PreferredWeek[]> {
    return this.preferredWeekRepository.remove(weeks);
  }

  async deleteWithDependencies(id: number): Promise<void> {
    const week = await this.findById(id);
    const days = await week.preferredDays;

    for (const day of days) {
      const hours = await day.preferredHours;
      await this.preferredHourService.remove(hours);
    }
    await this.preferredDayService.remove(days);
    await this.remove([week]);
  }

  public createNew = async (startDay: Date, user: User) => {
    const getNewPreferredDay = (day: Day): Promise<PreferredDay> => {
      const preferredDay = new PreferredDay();
      preferredDay.day = day;

      return this.preferredDayService.save(preferredDay);
    };
    const futureWeek = new PreferredWeek();
    futureWeek.startDay = startDay;
    futureWeek.user = Promise.resolve(user);
    const days: PreferredDay[] = [];

    days.push(await getNewPreferredDay(Day.monday));
    days.push(await getNewPreferredDay(Day.tuesday));
    days.push(await getNewPreferredDay(Day.wednesday));
    days.push(await getNewPreferredDay(Day.thursday));
    days.push(await getNewPreferredDay(Day.friday));
    days.push(await getNewPreferredDay(Day.saturday));
    days.push(await getNewPreferredDay(Day.sunday));

    futureWeek.preferredDays = Promise.resolve(days);

    return this.save(futureWeek);
  };

  async findAllUsersByStartDay(startDay: Date) {
    const preferredWeeks = await this.preferredWeekRepository
      .createQueryBuilder('week')
      .leftJoin('week.user', 'user')
      .leftJoin('user.roles', 'role')
      .leftJoin('role.resources', 'resource')
      .where('resource.name = :resource', {
        resource: resources.preferredWeeks.see,
      })
      .andWhere('user.active = :active', { active: true })
      .andWhere('week.startDay =:startDay', { startDay })
      .getMany();

    const usersWithoutWeek = await this.userService
      .getQueryBuilder('user')
      .leftJoin(
        'user.dbPreferredWeeks',
        'preferredWeek',
        'preferredWeek.startDay = :startDay',
        {
          startDay,
        },
      )
      .leftJoin('user.roles', 'role')
      .leftJoin('role.resources', 'resource')
      .where('resource.name = :resource', {
        resource: resources.preferredWeeks.see,
      })
      .andWhere('user.active = :active', { active: true })
      .andWhere('preferredWeek.startDay IS NULL', { startDay })
      .getMany();

    for (const userWithoutWeek of usersWithoutWeek) {
      const newWeek = await this.createNew(startDay, userWithoutWeek);
      preferredWeeks.push(newWeek);
    }

    return preferredWeeks;
  }

  getQueryBuilder(alias: string) {
    return this.preferredWeekRepository.createQueryBuilder(alias);
  }

  @Cron(CronExpression.EVERY_HOUR)
  private async checkDeadlineNotification(): Promise<void> {
    const deadlineNotification = await this.globalSettingsService.findByName(
      GlobalSettings.DEADLINE_NOTIFICATION,
    );
    const deadline = await this.globalSettingsService.findByName(
      GlobalSettings.PREFERRED_DEADLINE,
    );

    if (!deadlineNotification || !deadline)
      throw new InternalServerErrorException();

    const notificationDate = dayjs(deadline.value).subtract(
      +deadlineNotification.value,
      'hour',
    );
    const now = dayjs();

    if (
      notificationDate.day() === now.day() &&
      notificationDate.hour() === now.hour()
    ) {
      const notifyUsers = await this.userService.findByResource(
        resources.preferredWeeks.see,
      );

      this.notificationService.sendNotifications(
        notifyUsers,
        notifications.preferredWeek.deadline,
        routes.preferredWeeks.index,
      );
    }
  }
}

export default PreferredWeekService;
