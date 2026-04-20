import { forwardRef, Module } from '@nestjs/common';
import { JwtModule } from '@nestjs/jwt';

import AuthService from 'auth/auth.service';
import JwtStrategy from 'auth/jwt.strategy';
import apiConfig from 'config/api';
import UserModule from 'user/user.module';

@Module({
  imports: [
    forwardRef(() => UserModule),
    JwtModule.register({
      secret: apiConfig.jwt.secret,
      signOptions: { expiresIn: apiConfig.jwt.expiresIn },
    }),
  ],
  providers: [AuthService, JwtStrategy],
  exports: [AuthService],
})
export default class AuthModule {}
