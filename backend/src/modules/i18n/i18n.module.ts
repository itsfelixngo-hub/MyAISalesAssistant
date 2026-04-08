import * as path from 'path';
import { Module } from '@nestjs/common';
import { I18nModule, I18nJsonLoader, QueryResolver, HeaderResolver, CookieResolver, AcceptLanguageResolver } from 'nestjs-i18n';

@Module({
  imports: [
    I18nModule.forRoot({
      fallbackLanguage: 'en',
      loader: I18nJsonLoader,
      loaderOptions: {
        path: path.join(process.cwd(), 'i18n', 'locales'), // Load từ thư mục gốc
        watch: true,
        saveMissing: true,              // Save missing keys during development
        renderToString: true,
      },
      resolvers: [
        new QueryResolver(['lang', 'l']),
        new HeaderResolver(['x-custom-lang']),
        new CookieResolver(),
        new AcceptLanguageResolver,
      ],
    }),
  ],
  exports: [I18nModule],
})
export class CustomI18nModule {}
