import { Module } from '@nestjs/common';
import { FaqService } from './faq.service';
import { FaqController } from './faq.controller';
import { LanguageService } from '../language/language.service';
import { MongooseModule } from '@nestjs/mongoose';
import { Faq, FaqSchema } from './faq.schema';

@Module({
  imports: [
      MongooseModule.forFeature([{ name: Faq.name, schema: FaqSchema }])
    ],
  providers: [FaqService, LanguageService],
  controllers: [FaqController]
})
export class FaqModule {}
