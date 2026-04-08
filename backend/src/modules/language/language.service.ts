import { Injectable, Scope } from '@nestjs/common';

@Injectable({ scope: Scope.REQUEST }) // Ensures a new instance per request
export class LanguageService {
  private lang: string = 'en'; // Default language

  setLanguage(lang: string) {
    this.lang = lang || 'en'; // If undefined, fallback to English
  }

  getLanguage(): string {
    return this.lang;
  }
}
