import { Controller, Post, Body, Req, UseGuards } from '@nestjs/common';
import { AuthService } from './auth.service';
import { JwtAuthGuard } from './auth.guard';
import { LoginDto } from './dto/login.dto';
import { RegisterDto } from './dto/register.dto';
import {ApiBearerAuth, ApiBody} from "@nestjs/swagger";
import { Public } from '../decorator/public.decorator';

@Controller('auth')
export class AuthController {
  constructor(private readonly authService: AuthService) {}

  @Public()
  @Post('login')
  async login(@Body() loginDto: LoginDto) {
    return this.authService.validateUser(loginDto);
  }


  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('logout')
  async logout(@Req() req) {
    console.log('Decoded JWT User:', req.user);
    return this.authService.logout(req.user.userId); 
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('register')
  async register(@Body() registerDto: RegisterDto, @Req() req: any) {
    // console.log(req.user);
    return this.authService.registerUser(registerDto, req.user.profile.email);
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('refresh')
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        refreshToken: { type: '67e527b4c243b449354dec0b' },
      },
    },
  })
  @Post('refresh')
  async refresh(@Body('refreshToken') refreshToken: string) {
    return this.authService.refreshToken(refreshToken);
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        refreshToken: { type: '67e527b4c243b449354dec0b' },
      },
    },
  })
  @Post('revoke')
  async revoke(@Body('refreshToken') refreshToken: string) {
    return this.authService.revokeToken(refreshToken);
  }
}