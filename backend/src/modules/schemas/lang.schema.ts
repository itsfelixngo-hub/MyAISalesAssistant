import { Prop, SchemaFactory } from '@nestjs/mongoose';
import { Document } from 'mongoose';

export class langSchema extends Document {
  @Prop({ required: true, default: 'en' })
  lang?: string
}