import {
  BadRequestException,
  InternalServerErrorException,
  UnauthorizedException,
} from '@nestjs/common';
import {
  Args,
  Mutation,
  Parent,
  Query,
  ResolveProperty,
  Resolver,
} from '@nestjs/graphql';
import { Int } from 'type-graphql';

import AuthService from 'auth/auth.service';
import CurrentUser from 'auth/currentUser.decorator';
import Secured from 'auth/secured.guard';
import Branch from 'branch/branch.entity';
import BranchService from 'branch/branch.service';
import apiErrors from 'config/api/errors';
import resources from 'config/api/resources';
import { emailRegex, phoneRegex } from 'config/regexs';
import PreferredWeek from 'preferredWeek/preferredWeek.entity';
import RoleService from 'role/role.service';
import ShiftRoleType from 'shiftRoleType/shiftRoleType.entity';
import ShiftRoleTypeService from 'shiftRoleType/shiftRoleType.service';
import User from 'user/user.entity';
import UserService from 'user/user.service';
import ActionHistoryService from 'actionHistory/actionHistory.service';
import historyName from 'config/api/history';
import PreferredWeekService from 'preferredWeek/preferredWeek.service';
import Evaluation from 'evaluation/evalution.entity';
import EvaluationService from 'evaluation/evaluation.service';
import GlobalSettingsService from 'globalSettings/globalSettings.service';
import GlobalSettings from 'globalSettings/globalSettings.entity';
import ShiftWeekService from 'shiftWeek/shiftWeek.service';

import getNextMonday from '../utils/getNextMonday';
import NotificationService from '../notification/notification.service';
import notifications from '../config/api/notifications';
import routes from '../config/app/routes';
import EventNotification from '../eventNotification/eventNotification.entity';

@Resolver(() => User)
class UserResolver {
  constructor(
    private readonly userService: UserService,
    private readonly authService: AuthService,
    private readonly roleService: RoleService,
    private readonly branchService: BranchService,
    private readonly shiftRoleTypeService: ShiftRoleTypeService,
    private readonly actionHistoryService: ActionHistoryService,
    private readonly preferredWeekService: PreferredWeekService,
    private readonly evaluationService: EvaluationService,
    private readonly globalSettingsService: GlobalSettingsService,
    private readonly shiftWeekService: ShiftWeekService,
    private readonly notificationService: NotificationService,
  ) {}

  @Query(() => User)
  async userLogin(
    @Args({ name: 'email', type: () => String }) email: string,
    @Args({ name: 'password', type: () => String }) plainPassword: string,
    @CurrentUser() userId: number,
  ) {
    const user = await this.authService.validateUser(email, plainPassword);

    if (!user || !user.active) {
      throw new UnauthorizedException();
    }

    user.accessToken = await this.authService.login(user);
    user.lastLoginTime = new Date(Date.now());
    this.actionHistoryService.addRecord({
      name: historyName.login,
      userId: user.id,
    });

    return this.userService.save(user);
  }

  @Query(() => User)
  @Secured()
  async userGetLogged(@CurrentUser() userId: number) {
    return this.userService.findById(userId);
  }

  @Query(() => User)
  @Secured(resources.users.see)
  async userFindById(@Args({ name: 'id', type: () => Int }) id: number) {
    return this.userService.findById(id);
  }

  @Mutation(() => User)
  @Secured(resources.users.add)
  async userRegister(
    @Args('email') email: string,
    @Args('name') name: string,
    @Args('surname') surname: string,
    @CurrentUser() userId: number,
  ) {
    if (!emailRegex.test(email)) {
      throw new BadRequestException();
    }

    const hashedPassword = await this.userService.hashPassword(name.trim());

    let user = new User();
    user.createTime = new Date(Date.now());
    user.email = email.trim();
    user.password = hashedPassword;
    user.generatedPassword = name.trim();
    user.name = name.trim();
    user.surname = surname.trim();
    user = await this.userService.setRegistrationDefaults(user);
    user = await this.userService.save(user);
    await this.userService.createCurrentPreferredWeek(user);

    this.actionHistoryService.addRecord({
      name: historyName.user.register,
      userId,
      additionalData: { email, name, surname },
    });

    return user;
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangeRoles(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'rolesIds', type: () => [Int] }) rolesIds: number[],
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    const oldRoles = await user.roles;
    if (!user) throw new BadRequestException();
    const userRoles = [];

    for (const roleId of rolesIds) {
      const role = await this.roleService.findById(roleId);
      if (!role) throw new BadRequestException();
      if ((await user.roles).some(r => r.id === roleId)) {
        if (role.maxUsers <= (await role.dbUsers).length - 1)
          throw new BadRequestException(apiErrors.role.maxUsers);
      } else if (role.maxUsers <= (await role.dbUsers).length)
        throw new BadRequestException(apiErrors.role.maxUsers);
      userRoles.push(role);
    }

    user.roles = Promise.resolve(userRoles);

    this.actionHistoryService.addRecord({
      name: historyName.user.changeRoles,
      userId: currentUserId,
      additionalData: { user, before: oldRoles, after: userRoles },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userResetPassword(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const hashedPassword = await this.userService.hashPassword(user.name);

    user.password = hashedPassword;
    user.generatedPassword = user.name;
    user.passwordIsHashed = true;

    this.actionHistoryService.addRecord({
      name: historyName.user.resetPassword,
      userId: currentUserId,
      additionalData: { user },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured()
  async userResetMyPassword(
    @Args('oldPassword') oldPassword: string,
    @Args('newPassword') newPassword: string,
    @CurrentUser() userId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();
    if (user.passwordIsHashed) {
      if (
        !(await this.userService.comparePassword(oldPassword, user.password))
      ) {
        throw new BadRequestException(apiErrors.input.invalid);
      }
    } else if (oldPassword !== user.password) {
      throw new BadRequestException(apiErrors.input.invalid);
    }

    user.password = await this.userService.hashPassword(newPassword);
    user.passwordIsHashed = true;

    this.actionHistoryService.addRecord({
      name: historyName.user.resetMyPassword,
      userId,
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangeActive(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args('active') active: boolean,
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();
    user.active = active;
    user.approved = true;
    this.actionHistoryService.addRecord({
      name: historyName.user.changeActive,
      userId: currentUserId,
      additionalData: { user, active },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured()
  async userChangeDarkTheme(
    @Args('darkTheme') darkTheme: boolean,
    @CurrentUser() userId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();
    user.darkTheme = darkTheme;

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangePlanableBranches(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'branchIds', type: () => [Int] }) branchIds: number[],
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const oldBranches = await user.dbPlanableBranches;

    if (
      !(await this.authService.hasResources(userId, [
        /* resources.shift.canPlan */
      ]))
    )
      throw new BadRequestException();

    const userPlanableBranches = [];

    for (const branchId of branchIds) {
      const branch = await this.branchService.findById(branchId);
      if (!branch) throw new BadRequestException();
      userPlanableBranches.push(branch);
    }
    user.dbPlanableBranches = Promise.resolve(userPlanableBranches);

    this.actionHistoryService.addRecord({
      name: historyName.user.changePlanableBranches,
      userId: currentUserId,
      additionalData: {
        user,
        before: oldBranches,
        after: userPlanableBranches,
      },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userEdit(
    @Args({ name: 'id', type: () => Int }) id: number,
    @Args('name') name: string,
    @Args('surname') surname: string,
    @Args('email') email: string,
    @Args('hasOwnCar') hasOwnCar: boolean,
    @Args('phoneNumber') phoneNumber: string,
    @Args('receiveEmails') receiveEmails: boolean,
    @CurrentUser() currentUserId: number,
  ): Promise<User> {
    const user = await this.userService.findById(id);
    if (!user) throw new BadRequestException();

    const oldUser = { ...user };

    if (!emailRegex.test(email) || !phoneRegex.test(phoneNumber)) {
      throw new BadRequestException();
    }

    user.name = name;
    user.surname = surname;
    user.email = email;
    user.hasOwnCar = hasOwnCar;
    user.phoneNumber = phoneNumber;
    user.receiveEmails = receiveEmails;

    this.actionHistoryService.addRecord({
      name: historyName.user.edit,
      userId: currentUserId,
      additionalData: { before: oldUser, after: user },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured()
  async userEditMyself(
    @CurrentUser() userId: number,
    @Args({ name: 'hasOwnCar', nullable: true, type: () => Boolean })
    hasOwnCar?: boolean,
    @Args({ name: 'phoneNumber', nullable: true, type: () => String })
    phoneNumber?: string,
  ): Promise<User> {
    const user = await this.userService.findById(userId);
    if (!user) throw new InternalServerErrorException();

    const oldUser = { ...user };

    if (phoneNumber !== null && !phoneRegex.test(phoneNumber)) {
      throw new BadRequestException();
    }

    if (hasOwnCar !== null) user.hasOwnCar = hasOwnCar;
    if (phoneNumber !== null) user.phoneNumber = phoneNumber;

    this.actionHistoryService.addRecord({
      name: historyName.user.editMyself,
      userId,
      additionalData: { before: oldUser, after: user },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangeWorkingBranches(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'branchIds', type: () => [Int] }) branchIds: number[],
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const oldBranches = await user.dbWorkingBranches;

    const workingBranches: Branch[] = [];

    for (const branchId of branchIds) {
      const branch = await this.branchService.findById(branchId);
      if (!branch) throw new BadRequestException();
      workingBranches.push(branch);
    }

    const mainBranch = await user.dbMainBranch;

    if (mainBranch && !workingBranches.some(b => b.id === mainBranch.id))
      user.dbMainBranch = null;

    user.dbWorkingBranches = Promise.resolve(workingBranches);

    this.actionHistoryService.addRecord({
      name: historyName.user.changeWorkingBranches,
      userId: currentUserId,
      additionalData: { user, before: oldBranches, after: workingBranches },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangeWorkersShiftRoleTypes(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'shiftRoleTypeIds', type: () => [Int] })
    shiftRoleTypeIds: number[],
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const oldShiftRoleTypes = await user.dbWorkersShiftRoleTypes;

    const shiftRoleTypes = [];

    for (const shiftRoleTypeId of shiftRoleTypeIds) {
      const shiftRoleType = await this.shiftRoleTypeService.findById(
        shiftRoleTypeId,
      );
      if (!shiftRoleType) throw new BadRequestException();
      shiftRoleTypes.push(shiftRoleType);
    }

    user.dbWorkersShiftRoleTypes = Promise.resolve(shiftRoleTypes);

    await this.userService.save(user);

    this.actionHistoryService.addRecord({
      name: historyName.user.changeWorkersShiftRoleType,
      userId: currentUserId,
      additionalData: {
        user,
        before: oldShiftRoleTypes,
        after: shiftRoleTypes,
      },
    });

    return this.userService.findById(userId);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangeMainBranch(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'branchId', type: () => Int }) branchId: number,
    @CurrentUser() currentUserId: number,
  ) {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const oldMain = await user.dbMainBranch;

    const branch = await this.branchService.findById(branchId);
    if (!branch) throw new BadRequestException();

    const userBranches = await user.dbWorkingBranches;
    if (!userBranches.some(b => b.id === branch.id))
      throw new BadRequestException();

    user.dbMainBranch = Promise.resolve(branch);

    this.actionHistoryService.addRecord({
      name: historyName.user.changeMainBranch,
      userId: currentUserId,
      additionalData: { user, before: oldMain, after: branch },
    });

    return this.userService.save(user);
  }

  @Mutation(() => User)
  @Secured(resources.users.edit)
  async userChangePlanableShiftRoleTypes(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args({ name: 'shiftRoleTypeIds', type: () => [Int] })
    shiftRoleTypeIds: number[],
    @CurrentUser() currentUserId: number,
  ): Promise<User> {
    const user = await this.userService.findById(userId);
    if (!user) throw new BadRequestException();

    const oldShiftRoleTypes = await user.dbPlanableShiftRoleTypes;

    const shiftRoleTypes = [];

    for (const shiftRoleTypeId of shiftRoleTypeIds) {
      const shiftRoleType = await this.shiftRoleTypeService.findById(
        shiftRoleTypeId,
      );
      if (!shiftRoleType) throw new BadRequestException();
      shiftRoleTypes.push(shiftRoleType);
    }

    user.dbPlanableShiftRoleTypes = Promise.resolve(shiftRoleTypes);

    await this.userService.save(user);

    this.actionHistoryService.addRecord({
      name: historyName.user.changePlanableShiftRoleType,
      userId: currentUserId,
      additionalData: {
        user,
        before: oldShiftRoleTypes,
        after: shiftRoleTypes,
      },
    });

    return this.userService.findById(userId);
  }

  @Mutation(() => Boolean)
  async userRegisterMyself(
    @Args('email') email: string,
    @Args('name') name: string,
    @Args('surname') surname: string,
    @Args('password') password: string,
  ) {
    const oldUser = await this.userService.findByEmail(email);
    if (oldUser) throw new BadRequestException(apiErrors.db.duplicate);

    let user = new User();
    user.active = false;
    user.approved = false;
    user.email = email.trim();
    user.name = name.trim();
    user.createTime = new Date(Date.now());
    user.surname = surname.trim();
    user.password = await this.userService.hashPassword(password);
    user = await this.userService.setRegistrationDefaults(user);
    user = await this.userService.save(user);

    await this.userService.createCurrentPreferredWeek(user);

    const notifyUsers = await this.userService
      .getQueryBuilder('user')
      .leftJoin('user.roles', 'role')
      .leftJoin('role.resources', 'resource')
      .where('resource.name = :resource', {
        resource: resources.users.notifyAfterRegistration,
      })
      .getMany();

    this.notificationService.sendEventNotifications(
      EventNotification.NEW_USER_REGISTRATION,
      notifyUsers,
      `${routes.users.userDetail}?userId=${user.id}`,
      { name: user.name, surname: user.surname, email: user.email },
    );

    this.actionHistoryService.addRecord({
      name: historyName.user.registerMyself,
      userId: user.id,
      additionalData: { user },
    });

    return true;
  }

  @Mutation(() => Boolean)
  @Secured(resources.users.edit)
  async userRemove(
    @Args({ name: 'id', type: () => Int }) id: number,
  ): Promise<boolean> {
    const user = await this.userService.findById(id);
    if (!user) throw new BadRequestException();

    const history = await this.actionHistoryService.findByUser(user);
    await this.actionHistoryService.remove(history);

    const preferredWeeks = await user.dbPreferredWeeks;
    for (const preferredWeek of preferredWeeks) {
      await this.preferredWeekService.deleteWithDependencies(preferredWeek.id);
    }

    if (user.approved === false && user.active === false) {
      await this.userService.delete(user);
    }

    return true;
  }

  @Mutation(() => User)
  @Secured(resources.evaluation.add)
  async userAddEvaluation(
    @Args({ name: 'userId', type: () => Int }) userId: number,
    @Args('description') description: string,
    @Args('positive') positive: boolean,
    @CurrentUser() currentUserId: number,
  ): Promise<User> {
    if (description.length < 10) {
      throw new BadRequestException();
    }

    const user = await this.userService.findById(userId);
    if (!user || !user.active) throw new BadRequestException();

    const evaluationCooldown = await this.globalSettingsService.findByName(
      GlobalSettings.EVALUATION_COOLDOWN,
    );
    if (!evaluationCooldown) throw new InternalServerErrorException();

    const cooldownDate = new Date(Date.now());
    cooldownDate.setHours(cooldownDate.getHours() - +evaluationCooldown.value);

    const afterCooldown = await this.evaluationService.afterCooldown(
      cooldownDate,
      userId,
      currentUserId,
    );

    if (!afterCooldown)
      throw new BadRequestException(apiErrors.evaluation.cooldown);

    const evaluater = await this.userService.findById(currentUserId);

    let evaluation = new Evaluation();
    evaluation.date = new Date(Date.now());
    evaluation.description = description;
    evaluation.positive = positive;
    evaluation.user = Promise.resolve(user);
    evaluation.evaluater = Promise.resolve(evaluater);
    evaluation = await this.evaluationService.save(evaluation);

    user.evaluation = Promise.resolve([...(await user.evaluation), evaluation]);

    return user;
  }

  @ResolveProperty(() => [Branch])
  async planableBranches(
    @Parent() parent: User,
    @CurrentUser() userId: number,
  ) {
    if (
      (await this.authService.hasResources(userId, [resources.users.see])) ||
      parent.id === userId
    ) {
      return parent.dbPlanableBranches;
    }

    return [];
  }

  @ResolveProperty(() => [ShiftRoleType])
  async planableShiftRoleTypes(
    @Parent() parent: User,
    @CurrentUser() userId: number,
  ): Promise<ShiftRoleType[]> {
    if (
      (await this.authService.hasResources(userId, [resources.users.see])) ||
      parent.id === userId
    ) {
      return parent.dbPlanableShiftRoleTypes;
    }

    return [];
  }

  @ResolveProperty(() => [Branch])
  async workingBranches(@Parent() parent: User, @CurrentUser() userId: number) {
    if (await this.authService.hasResources(userId, [resources.users.see])) {
      return parent.dbWorkingBranches;
    }

    return [];
  }

  @ResolveProperty(() => [ShiftRoleType])
  async workersShiftRoleTypes(
    @Parent() parent: User,
    @CurrentUser() userId: number,
  ) {
    if (await this.authService.hasResources(userId, [resources.users.see])) {
      return parent.dbWorkersShiftRoleTypes;
    }

    return [];
  }

  @ResolveProperty(() => Branch, { nullable: true })
  async mainBranch(@Parent() parent: User, @CurrentUser() userId: number) {
    if (await this.authService.hasResources(userId, [resources.users.see]))
      return parent.dbMainBranch;

    return null;
  }

  @ResolveProperty(() => Int)
  async workingBranchesCount(@Parent() parent: User) {
    return (await parent.dbWorkingBranches).length;
  }

  @ResolveProperty(() => [PreferredWeek])
  async preferredWeeks(@Parent() parent: User, @CurrentUser() userId: number) {
    if (
      (await this.authService.hasResources(userId, [
        /* resources.shift.canPlan */
      ])) ||
      parent.id === userId
    ) {
      return parent.dbPreferredWeeks;
    }

    return [];
  }

  @ResolveProperty(() => [String])
  async workingBranchNames(@Parent() parent: User) {
    return (await parent.dbWorkingBranches).map(b => b.name);
  }

  @ResolveProperty(() => String)
  async mainBranchName(@Parent() parent: User) {
    return (await parent.dbMainBranch)?.name || '';
  }

  @ResolveProperty(() => [String])
  async shiftRoleTypeNames(@Parent() parent: User) {
    return (await parent.dbWorkersShiftRoleTypes).map(s => s.name);
  }

  @ResolveProperty(() => [Evaluation])
  async evaluation(
    @Parent() parent: User,
    @CurrentUser() userId: number,
  ): Promise<Evaluation[]> {
    if (
      parent.id === userId ||
      (await this.authService.hasResources(userId, [
        resources.evaluation.history,
      ]))
    )
      return parent.evaluation;

    return [];
  }

  @ResolveProperty(() => Int, { nullable: true })
  async totalEvaluationScore(
    @Parent() parent: User,
    @CurrentUser() userId: number,
  ): Promise<number> {
    if (
      parent.id !== userId &&
      !(await this.authService.hasResources(userId, [
        resources.evaluation.history,
        resources.weekPlanning.plan,
      ]))
    )
      return null;

    const ttl = await this.globalSettingsService.findByName(
      GlobalSettings.EVALUATION_TTL,
    );
    if (!ttl) throw new InternalServerErrorException();

    const ttlDate = new Date(Date.now());
    ttlDate.setDate(ttlDate.getDate() - +ttl.value);

    const evaluation = await this.evaluationService.findAfterDate(
      ttlDate,
      parent.id,
    );

    let score = 0;
    for (const e of evaluation) {
      if (e.positive) {
        score += 1;
      } else {
        score -= 1;
      }
    }

    return score;
  }

  @ResolveProperty(() => Boolean)
  async unconfirmedNextPreferredWeek(@Parent() parent: User): Promise<boolean> {
    const preferredWeek = await this.preferredWeekService.findByStartDayAndUserId(
      getNextMonday(0),
      parent.id,
    );
    if (!preferredWeek || preferredWeek?.confirmed) return false;

    const workingBranches = await parent.dbWorkingBranches;
    const workingBranchesIds = workingBranches.map(b => b.id);

    if (workingBranchesIds.length === 0) return false;

    const shiftWeeks = await this.shiftWeekService.findByBranchIdsAndStartDay(
      workingBranchesIds,
      new Date(preferredWeek.startDay),
    );

    if (shiftWeeks.length !== workingBranchesIds.length) return false;

    return !shiftWeeks.some(w => !w.published);
  }

  @ResolveProperty(() => Boolean)
  async notificationsActivated(@Parent() parent: User): Promise<boolean> {
    return (await parent.notifications).length > 0;
  }
}

export default UserResolver;
