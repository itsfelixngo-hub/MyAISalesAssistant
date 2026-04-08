import { Module, NestModule, MiddlewareConsumer } from '@nestjs/common';
import { LanguageService } from './language.service';
import { LanguageController } from './language.controller';
import { LanguageMiddleware } from 'src/common/language.middleware';

@Module({
  controllers: [LanguageController],
  providers: [LanguageService],
  exports: [LanguageService]
})
export class LanguageModule implements NestModule {
  configure(consumer: MiddlewareConsumer) {
    consumer.apply(LanguageMiddleware).forRoutes('*'); // Apply middleware globally
  }
}
