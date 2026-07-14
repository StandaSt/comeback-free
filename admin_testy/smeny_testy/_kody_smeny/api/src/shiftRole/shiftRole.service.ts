import {
  BadRequestException,
  Injectable,
  InternalServerErrorException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

import GlobalSettingsService from 'globalSettings/globalSettings.service';
import PreferredHourService from 'preferredHour/preferredHour.service';
import UserService from 'user/user.service';

import apiErrors from '../config/api/errors';
import GlobalSettings from '../globalSettings/globalSettings.entity';
import ShiftHour from '../shiftHour/shiftHour.entity';
import ShiftHourService from '../shiftHour/shiftHour.service';
import hourIntervalChecker from '../utils/hourIntervalChecker';

import HourArg from './args/hour.arg';
import ShiftRole from './shiftRole.entity';

@Injectable()
class ShiftRoleService {
  constructor(
    @InjectRepository(ShiftRole)
    private readonly shiftRoleRepository: Repository<ShiftRole>,
    private readonly userService: UserService,
    private readonly shiftHourService: ShiftHourService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly preferredHourService: PreferredHourService,
  ) {}

  async save(shiftRole: ShiftRole): Promise<ShiftRole> {
    return this.shiftRoleRepository.save(shiftRole);
  }

  async findById(id: number): Promise<ShiftRole> {
    return this.shiftRoleRepository.findOne(id);
  }

  async remove(shiftRoles: ShiftRole[]): Promise<ShiftRole[]> {
    return this.shiftRoleRepository.remove(shiftRoles);
  }

  async findShiftHourByHour(startHour: number, shiftRole: ShiftRole) {
    const hours = await shiftRole.shiftHours;

    return hours.find(h => h.startHour === startHour);
  }

  async changeHours(
    shiftRole: ShiftRole,
    hours: HourArg[],
  ): Promise<{ shiftRole: ShiftRole; removedHours: ShiftHour[] }> {
    const shiftHours = [];

    for (const h of hours) {
      if (h.startHour < 0 || h.startHour > 23) throw new BadRequestException();
      let hour = await this.findShiftHourByHour(h.startHour, shiftRole);
      if (!hour) {
        hour = new ShiftHour();
        hour.startHour = h.startHour;
      }

      hour.shiftRole = Promise.resolve(shiftRole);

      shiftHours.push(await this.shiftHourService.save(hour));
    }
    const shiftRoleHours = await shiftRole.shiftHours;

    const removedHours = shiftRoleHours.filter(
      h => !shiftHours.some(sh => sh.startHour === h.startHour),
    );

    // eslint-disable-next-line no-param-reassign
    shiftRole.shiftHours = Promise.resolve(shiftHours);

    return { shiftRole, removedHours };
  }

  async unassignWorker(shiftRoleId: number, from: number, to: number) {
    const shiftRole = await this.findById(shiftRoleId);
    if (!shiftRole) throw new BadRequestException();

    const dayStart = await this.globalSettingsService.findByName(
      GlobalSettings.DAY_START,
    );
    if (!dayStart) throw new InternalServerErrorException();

    if (!hourIntervalChecker(from, to, +dayStart.value)) {
      throw new BadRequestException();
    }

    const shiftHours = await shiftRole.shiftHours;
    const newShiftHours = [];

    for (let i = from; i !== to; i++) {
      if (i === 24) {
        if (to === 0) break;
        i = 0;
      }

      const shiftHour = shiftHours.find(s => s.startHour === i);
      if (shiftHour) {
        const preferredHour = await shiftHour.preferredHour;
        shiftHour.dbWorker = null;
        shiftHour.preferredHour = null;
        await this.shiftHourService.save(shiftHour);
        if (preferredHour?.visible === false) {
          await this.preferredHourService.delete(preferredHour.id);
        }
      } else {
        throw new BadRequestException(apiErrors.shiftRole.hoursOutOfRange);
      }
    }

    for (const newShiftHour of newShiftHours) {
      await this.shiftHourService.save(newShiftHour);
    }

    return shiftRole;
  }

  async removeWithDependencies(id: number) {
    const shiftRole = await this.findById(id);
    if (!shiftRole) throw new BadRequestException();

    const shiftHours = await shiftRole.shiftHours;
    shiftRole.shiftHours = Promise.resolve([]);
    await this.save(shiftRole);

    for (let hourIndex = 0; hourIndex < shiftHours.length; hourIndex++) {
      const shiftHour = shiftHours[hourIndex];

      const preferredHour = await shiftHour.preferredHour;

      shiftHour.dbWorker = null;
      shiftHour.preferredHour = null;
      await this.shiftHourService.save(shiftHour);
      if (preferredHour?.visible === false) {
        await this.preferredHourService.delete(preferredHour.id);
      }
    }

    await this.remove([shiftRole]);
  }

  async isEmpty(shiftRole: ShiftRole): Promise<boolean> {
    const shiftHours = await shiftRole.shiftHours;
    for (const shiftHour of shiftHours) {
      if (await shiftHour.dbWorker) {
        return false;
      }
    }

    return true;
  }
}

export default ShiftRoleService;
