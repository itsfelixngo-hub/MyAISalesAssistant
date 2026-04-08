import { Module } from '@nestjs/common';
import { OptionsController } from './options.controller';
import { OptionsService } from './options.service';
import { MongooseModule } from '@nestjs/mongoose';
import { Option,optionSchema } from './option.schema';
import { LanguageService } from '../language/language.service';

@Module({
  imports: [
    MongooseModule.forFeature([{ name: Option.name, schema: optionSchema }]),
  ],
  providers: [OptionsService, LanguageService],
  controllers: [OptionsController],
  exports: [OptionsService]
})
export class OptionsModule {}
