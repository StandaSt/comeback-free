import { Injectable } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';

import User from 'user/user.entity';
import UserService from 'user/user.service';

@Injectable()
class AuthService {
  constructor(
    private readonly userService: UserService,
    private readonly jwtService: JwtService,
  ) {}

  async validateUser(email: string, plainPassword: string): Promise<User> {
    const user = await this.userService.findByEmail(email);
    if (
      user &&
      user.passwordIsHashed &&
      (await this.userService.comparePassword(plainPassword, user.password))
    ) {
      return user;
    }
    if (user && !user.passwordIsHashed && plainPassword === user.password) {
      return user;
    }

    return null;
  }

  async login(user: User) {
    const payload = { sub: user.id };

    return this.jwtService.sign(payload);
  }

  async hasResources(userId: number, requiredResources: string[]) {
    if (requiredResources.length === 0) return true;

    const userRoles = await (await this.userService.findById(userId)).roles;

    if (userRoles === undefined) return false;

    const userResourcesMap = new Map<number, string>();
    // eslint-disable-next-line no-unused-expressions
    for (const role of userRoles) {
      const resources = await role.resources;
      for (const resource of resources) {
        userResourcesMap.set(resource.id, resource.name);
      }
    }

    const userResources = Array.from(userResourcesMap.values());

    // eslint-disable-next-line consistent-return
    for (const resource of requiredResources) {
      if (userResources.some(userResource => userResource === resource))
        return true;
    }

    return false;
  }
}

export default AuthService;
