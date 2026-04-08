import { Strategy, ExtractJwt } from 'passport-jwt';
import { PassportStrategy } from '@nestjs/passport';
import { Injectable } from '@nestjs/common';
import { UsersService } from '../users/users.service';
import { ConfigService } from '@nestjs/config';
import {TokenService} from "./token.service";
import {ExErrorException} from "../../common/error.filter";
import {I18nService} from "nestjs-i18n";

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  constructor(
    private readonly configService: ConfigService,
    private readonly usersService: UsersService,
    private readonly tokenService: TokenService,
    private readonly i18n: I18nService
  ) {
    const jwtSecret = configService.get<string>('JWT_SECRET');
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey: jwtSecret || `secretKey`,
    });
  }

  async validate(payload: any) {
    // console.log("payload:", payload);
    const revoked = await this.tokenService.isTokenRevoked(payload.refreshToken);
    console.log(revoked);
    if (revoked) {
      throw new ExErrorException(
          'TOKEN_IS_REVOKED',
          403,
          this.i18n.translate('errors.TOKEN_IS_REVOKED')
      );
    }
    const profile = await this.usersService.profileById(payload.userId);
    return { userId: payload.userId, profile };
  }
}