import { MiddlewareConsumer, Module, NestModule } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { envValidationSchema } from 'src/common/env.validation';
import { DatabaseModule } from './common/database.module';
import { LoggerMiddleware } from './common/logger.middleware';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { AuthModule } from './modules/auth/auth.module';
import { UsersModule } from './modules/users/users.module';
import { OptionsModule } from './modules/options/options.module';
import { FilesModule } from './modules/files/files.module';
import { ServeStaticModule } from '@nestjs/serve-static';
import { join } from 'path';
import { I18nMiddleware } from 'nestjs-i18n';
import { CustomI18nModule } from './modules/i18n/i18n.module';
import { LanguageModule } from './modules/language/language.module';
import { PostsModule } from './modules/posts/posts.module';
import { APP_GUARD } from '@nestjs/core';
import { JwtAuthGuard } from './modules/auth/auth.guard';
import { RolesGuard } from './modules/auth/roles.guard';
import { ContactModule } from './modules/contact/contact.module';
import { IndexSyncService } from './modules/services/IndexSyncService';
import { FaqModule } from './modules/faq/faq.module';
import { IpBlockMiddleware } from './common/ipBlocked.middleware';
import { RequestClassifierMiddleware } from './common/request-classifier.middleware';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true, validationSchema: envValidationSchema, }), // Load .env toàn bộ app
    ServeStaticModule.forRoot({
      rootPath: join(process.cwd(), 'storage'), // Uses absolute path
      serveRoot: '/storage', // Files accessible at `/storage/*`
    }),
    DatabaseModule,  // Import DatabaseModule vào AppModule
    AuthModule,
    UsersModule,
    OptionsModule,
    FilesModule,
    CustomI18nModule,
    LanguageModule,
    PostsModule,
    ContactModule,
    FaqModule,
  ],
  providers: [
    AppService,
    {
      provide: APP_GUARD,
      useClass: JwtAuthGuard,
    },
    {
      provide: APP_GUARD,
      useClass: RolesGuard,
    },
    IndexSyncService
  ],
  controllers: [AppController]
})
export class AppModule implements NestModule {
  configure(consumer: MiddlewareConsumer) {
    consumer
      .apply(LoggerMiddleware, I18nMiddleware, IpBlockMiddleware, RequestClassifierMiddleware)
      .forRoutes('*');
  }
}
