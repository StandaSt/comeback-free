import { forwardRef, Inject, Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { In, Like, Repository } from 'typeorm';
import { compare, hash } from 'bcrypt';

import BranchService from 'branch/branch.service';
import apiConfig from 'config/api';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import OrderByArg from 'paginator/orderBy.arg';
import PreferredWeekService from 'preferredWeek/preferredWeek.service';
import RoleService from 'role/role.service';
import ShiftRoleTypeService from 'shiftRoleType/shiftRoleType.service';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';
import UserFilterArg from 'user/paginator/args/userFilter.arg';
import User from 'user/user.entity';

import getNextMonday from '../utils/getNextMonday';

@Injectable()
class UserService {
  constructor(
    @InjectRepository(User) private readonly userRepository: Repository<User>,
    @Inject(forwardRef(() => ShiftWeekService))
    private readonly shiftWeekService: ShiftWeekService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly roleService: RoleService,
    private readonly shiftRoleTypeService: ShiftRoleTypeService,
    private readonly branchService: BranchService,
    @Inject(forwardRef(() => PreferredWeekService))
    private readonly preferredWeekService: PreferredWeekService,
  ) {}

  async save(user: User) {
    return this.userRepository.save(user);
  }

  async findById(userId: number) {
    return this.userRepository.findOne(userId);
  }

  findByResource(resourceName: string): Promise<User[]> {
    return this.userRepository
      .createQueryBuilder('user')
      .leftJoin('user.roles', 'role')
      .leftJoin('role.resources', 'resource')
      .where('resource.name = :name', { name: resourceName })
      .getMany();
  }

  delete(user: User): void {
    this.userRepository.remove(user);
  }

  getQueryBuilder(alias: string) {
    return this.userRepository.createQueryBuilder(alias);
  }

  async paginate(
    limit: number,
    offset: number,
    filter: UserFilterArg,
    orderBy?: OrderByArg,
  ) {
    const order = orderBy
      ? { [`user.${orderBy.fieldName}`]: orderBy.type }
      : {};

    const qb = this.userRepository
      .createQueryBuilder('user')
      .leftJoin('user.notifications', 'notification')
      .where('user.email like :email', { email: `%${filter.email}%` })
      .andWhere('user.name like :name', { name: `%${filter.name}%` })
      .andWhere('user.surname like :surname', {
        surname: `%${filter.surname}%`,
      })
      .andWhere('user.active in (:...active)', {
        active: filter.active.length > 0 ? filter.active : [true, false],
      })
      .andWhere('user.approved in (:...approved)', {
        approved: filter.approved.length > 0 ? filter.approved : [true, false],
      });

    if (filter.notificationsActivated.length === 1) {
      if (filter.notificationsActivated[0] === false) {
        qb.andWhere('notification.id IS NULL');
      } else if (filter.notificationsActivated[0] === true) {
        qb.andWhere('notification.id IS NOT NULL');
      }
    }

    qb.orderBy(order).take(limit).skip(offset);

    return qb.getMany();
  }

  async getTotalCount(filter?: UserFilterArg): Promise<number> {
    const qb = this.userRepository
      .createQueryBuilder('user')
      .leftJoin('user.notifications', 'notification')
      .where('user.email like :email', { email: `%${filter.email}%` })
      .andWhere('user.name like :name', { name: `%${filter.name}%` })
      .andWhere('user.surname like :surname', {
        surname: `%${filter.surname}%`,
      })
      .andWhere('user.active in (:...active)', {
        active: filter.active.length > 0 ? filter.active : [true, false],
      })
      .andWhere('user.approved in (:...approved)', {
        approved: filter.approved.length > 0 ? filter.approved : [true, false],
      });

    if (filter.notificationsActivated.length === 1) {
      if (filter.notificationsActivated[0] === false) {
        qb.andWhere('notification.id IS NULL');
      } else if (filter.notificationsActivated[0] === true) {
        qb.andWhere('notification.id IS NOT NULL');
      }
    }

    return qb.getCount();
  }

  async findByEmail(email: string): Promise<User> {
    return this.userRepository.findOne({ email });
  }

  async comparePassword(plain: string, hashed: string) {
    return compare(plain, hashed);
  }

  async hashPassword(plain: string) {
    return hash(plain, apiConfig.hash.saltRounds);
  }

  async setRegistrationDefaults(user: User) {
    const defaultRoles = await this.roleService.findRegistrationDefaults();
    const defaultShiftRoles = await this.shiftRoleTypeService.findRegistrationDefaults();
    const branches = await this.branchService.findActive();

    user.roles = Promise.resolve(defaultRoles);
    user.dbWorkersShiftRoleTypes = Promise.resolve(defaultShiftRoles);
    user.dbWorkingBranches = Promise.resolve(branches);

    return user;
  }

  async createCurrentPreferredWeek(user: User) {
    const date = getNextMonday(-1);
    await this.preferredWeekService.createNew(date, user);
  }
}

export default UserService;
