import { Injectable, NestMiddleware } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';

@Injectable()
export class LanguageMiddleware implements NestMiddleware {
  use(req: Request, res: Response, next: NextFunction) {
    let lang = req.headers['accept-language'];

    if (lang) {
      // Extract only the first language from the Accept-Language header
      //'Accept-Language: fr,en;q=0.8'
      lang = lang.split(',')[0].trim();
    } else {
      lang = 'en'; // Default to English if no language is provided
    }

    req.headers['accept-language'] = lang; // Ensure a clean, single language

    next();
  }
}
