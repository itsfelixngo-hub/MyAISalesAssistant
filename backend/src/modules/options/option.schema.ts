import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document, Types } from 'mongoose';
import { langSchema } from '../schemas/lang.schema';

@Schema({ timestamps: true })
export class Option extends langSchema {

  @Prop({ required: true, unique: true })
  name: string;

  @Prop({ required: true, type: String })
  value: string;

  @Prop({ required: true, default: 1 })
  autoLoad: number;
}
export const optionSchema = SchemaFactory.createForClass(Option);
