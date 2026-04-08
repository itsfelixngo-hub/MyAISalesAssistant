import { Injectable, NestMiddleware } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';
import logger from 'src/utils/logger';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class RequestClassifierMiddleware implements NestMiddleware {
    constructor(private readonly configService: ConfigService) { }
    use(req: Request, res: Response, next: NextFunction) {
        const enableLogRequest = this.configService.get<boolean>('LOG_REQUEST');
        console.log(`LOG_REQUEST: ${enableLogRequest}`);
        if (!enableLogRequest) {
            return next();
        }

        const ua = (req.headers['user-agent'] || '').toLowerCase();
        const forwarded = req.headers['x-forwarded-for'];
        const ip = typeof forwarded === 'string'
            ? forwarded.split(',')[0].trim()
            : req.socket?.remoteAddress?.replace(/^::ffff:/, '') || '';

        const isBot = /curl|bot|wget|python|scrapy|httpclient|spider/.test(ua);
        const isBrowser = /mozilla|chrome|safari|firefox/.test(ua);
        const isAxios = /axios|node-fetch|fetch/.test(ua);
        const isDockerInternal = /^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(ip);

        let classification = 'UNKNOWN';

        if (isBot) {
            classification = 'BOT';
            console.warn(`[BOT] 🤖 ${ip} — UA: ${ua}`);
        } else if (isDockerInternal) {
            classification = 'DOCKER';
            console.log(`[DOCKER] 🐳 ${ip} — UA: ${ua}`);
        } else if (isAxios) {
            classification = 'FE';
            console.log(`[FE] ⚡️ Axios call from ${ip}`);
        } else if (isBrowser) {
            classification = 'BROWSER';
            console.log(`[BROWSER] 🌐 Browser call from ${ip}`);
        } else {
            console.warn(`[UNKNOWN] ❓ ${ip} — UA: ${ua}`);
        }

        const logEntry = `${new Date().toISOString()},${ip},"${ua}",${classification},"${req.method}","${req.originalUrl}"`;
        logger.info(logEntry);

        next();

    }
}
