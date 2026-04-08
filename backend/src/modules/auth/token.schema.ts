import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document, Types } from 'mongoose';

@Schema({ timestamps: true })
export class Token extends Document {
 @Prop({ type: Types.ObjectId, ref: 'User', required: true }) 
  userId: Types.ObjectId;

  @Prop({ required: true })
  refreshToken: string;

  @Prop({ default: false })
  revoked: boolean;

  createdAt?: Date; 
  updatedAt?: Date;
}

export const TokenSchema = SchemaFactory.createForClass(Token);