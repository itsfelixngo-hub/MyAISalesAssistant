import { Module } from '@nestjs/common';
import { ContactService } from './contact.service';
import { ContactController } from './contact.controller';
import { Contact, contactSchema } from './contact.schema';
import { MongooseModule } from '@nestjs/mongoose';
import { LanguageService } from '../language/language.service';

@Module({
  imports: [
      MongooseModule.forFeature([{ name: Contact.name, schema: contactSchema }])
    ],
  providers: [ContactService, LanguageService],
  controllers: [ContactController],
  exports: [MongooseModule.forFeature([{ name: Contact.name, schema: contactSchema }])]
})
export class ContactModule {}
