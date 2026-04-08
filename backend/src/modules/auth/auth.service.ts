import { Injectable } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
// import * as jwt from 'jsonwebtoken';
import { UsersService } from '../users/users.service';
import { TokenService } from './token.service';
import { LoginDto } from './dto/login.dto';
import { RegisterDto } from './dto/register.dto';
import * as bcrypt from 'bcrypt';
import { randomBytes } from 'crypto';
import { ExErrorException } from 'src/common/error.filter';
import {I18nService} from "nestjs-i18n";

@Injectable()
export class AuthService {
  constructor(
    private readonly usersService: UsersService,
    private readonly jwtService: JwtService,
    private readonly tokenService: TokenService,
    private readonly i18n: I18nService
  ) { }

  async validateUser(loginDto: LoginDto, lang?:string): Promise<{ accessToken: string, refreshToken: string, profile: object }> {
    const user = await this.usersService.findByEmail(loginDto.email);
    if (!user || !(await bcrypt.compare(loginDto.password, user.password))) {
      throw new ExErrorException(
          'INVALID_CREDENTIALS',
          403,
          this.i18n.translate('errors.INVALID_CREDENTIALS', { lang })
          );
    }
    return this.generateTokens(user._id.toString());
  }

  async logout(userId: string, lang?:string) {
    try {
      await this.tokenService.revokeTokenAll(userId);
      return { message: this.i18n.translate( 'msg.auth.TOKENS_REVOKE_SUCCESS', { lang }) };
    } catch (error) {
      throw new ExErrorException(error.message, error.status);
    }
  }

  async registerUser(registerDto: RegisterDto, email: string,  lang?:string) {
    const existingUser = await this.usersService.findByEmail(registerDto.email);
    if (existingUser) {
      throw new ExErrorException(
          'EMAIL_EXIST',
          409,
          this.i18n.translate('errors.EMAIL_EXIST', { lang })
          );
    }
    try {
      registerDto.password = await bcrypt.hash(registerDto.password, 10);
      registerDto.createBy = email;

      await this.usersService.createUser(registerDto);
      return { message: this.i18n.translate( 'msg.auth.USER_REGISTER_SUCCESS', { lang }) };
    } catch (error) {
      throw new ExErrorException(
        'USER_REGISTER_FAIL',
        500,
        this.i18n.translate('errors.USER_REGISTER_FAIL', { lang }),
        error.response.errors.response.errorCode
        );
    }
  }

  async generateTokens(userId: string, lang?:string) {
    const getUser = await this.usersService.profileById(userId);
    if (!getUser) throw new ExErrorException('USER_NOT_FOUND',404,this.i18n.translate('errors.USER_NOT_FOUND', { lang }));
    const { password, _id, __v, ...profile } = getUser;
    //console.log(profile);

    try {
      const refreshToken = randomBytes(32).toString('hex');
      const hashedRefreshToken = await bcrypt.hash(refreshToken, 10);

      const payload =  { userId, refreshToken };
      const accessToken = this.jwtService.sign(payload);

      /**
       * Logs debug
       */
      // const decoded = jwt.decode(accessToken) as any;

      // console.log('Access Token:', accessToken);
      // console.log('Decoded Payload:', decoded);
      // console.log('Expires At (UNIX):', decoded?.exp);
      // console.log('Expires At (UTC):', new Date(decoded?.exp * 1000).toISOString());

      await this.tokenService.saveRefreshToken(userId, hashedRefreshToken);

      return { accessToken, refreshToken, profile };
    } catch (error) {
      throw new ExErrorException(error.message, error.status);
    }
  }

  async refreshToken(refreshToken: string, lang?:string) {
    const token = await this.tokenService.findValidToken(refreshToken);
    if (!token) {
      throw new ExErrorException(
          'INVALID_REFRESH_TOKEN',
          403,
          this.i18n.translate( 'msg.auth.USER_REGISTER_SUCCESS', { lang })
      );
    }
    return this.generateTokens(token.userId.toString());
  }

  async revokeToken(refreshToken: string, lang?:string) {
    try {
      await this.tokenService.revokeToken(refreshToken);
      return { message: this.i18n.translate( 'msg.auth.TOKEN_REVOKE_SUCCESS', { lang }) };
    } catch (error) {
      throw new ExErrorException('SERVER_ERROR', 500, error.message);
    }
  }


}