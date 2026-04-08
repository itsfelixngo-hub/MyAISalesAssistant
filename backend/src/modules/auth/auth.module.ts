import { Global, Module } from '@nestjs/common';
import { JwtModule } from '@nestjs/jwt';
import { MongooseModule } from '@nestjs/mongoose';
import { PassportModule } from '@nestjs/passport';
import { ConfigModule, ConfigService } from '@nestjs/config';
import ms, { type StringValue } from 'ms';

import { AuthService } from './auth.service';
import { AuthController } from './auth.controller';
import { JwtStrategy } from './auth.strategy';
import { UsersModule } from '../users/users.module';
import { TokenService } from './token.service';
import { Token, TokenSchema } from './token.schema';

@Global()
@Module({
  imports: [
    PassportModule,
    ConfigModule,
    JwtModule.registerAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (configService: ConfigService) => {
        const secret = configService.getOrThrow<string>('JWT_SECRET');
        const rawExpires = configService.getOrThrow<string>('JWT_EXPIRES_IN'); // e.g. "1h"
        const seconds = Math.floor(ms(rawExpires as StringValue) / 1000);

        return {
          secret,
          signOptions: { expiresIn: seconds },
        };
      },
    }),
    UsersModule,
    MongooseModule.forFeature([{ name: Token.name, schema: TokenSchema }]),
  ],
  controllers: [AuthController],
  providers: [AuthService, JwtStrategy, TokenService],
  exports: [AuthService, JwtModule, TokenService, JwtStrategy],
})
export class AuthModule {}
