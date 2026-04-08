import { Injectable, NestMiddleware, UnauthorizedException } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class IpBlockMiddleware implements NestMiddleware {
  constructor(private readonly configService: ConfigService) {}

  use(req: Request, res: Response, next: NextFunction) {
    const isBlockingEnabled = this.configService.get<boolean>('BLOCK_IP');
    if (!isBlockingEnabled) {
      return next();
    }

    const rawIp =
      req.headers['x-forwarded-for']?.toString() ||
      req.socket?.remoteAddress ||
      '';
    const normalizedIp = rawIp.replace(/^::ffff:/, '');

    const blackList = this.configService.get<string>('BLACK_LIST_IP', ';');
    const blockedIps = blackList.split(';').filter(Boolean);

    const isBlocked = blockedIps.some((blocked) => {
      if (blocked.endsWith('*')) {
        const prefix = blocked.slice(0, -1);
        return normalizedIp.startsWith(prefix);
      }
      return normalizedIp === blocked;
    });

    if (isBlocked) {
      console.warn(`[IPBlock] ❌ Blocked IP: ${normalizedIp}`);
      throw new UnauthorizedException('Blocked IP');
    }

    next(); // chỉ gọi nếu IP không bị chặn
  }
}
