import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document, Types } from 'mongoose';
import { langSchema } from '../schemas/lang.schema';

@Schema({ timestamps: true })
export class Post extends langSchema {
  @Prop({ required: true })
  title: string;

  @Prop({ required: false })
  slug: string;

  @Prop()
  slugOld: string;

  @Prop({ required: true })
  content: string;

  @Prop({ enum: ['new', 'hidden', 'pending', 'processed', 'abort', 'posted', 'scheduled'], default: 'new' })
  status: string;

  @Prop({ enum: ['top_university', 'list_of_university', 'majors', 'topik_exams', 'post', 'page', 'news', 'fee', 'scholarship', 'visa', 'guide', 'knowledge'], default: 'post' })
  type: string;

  @Prop()
  scheduledAt?: Date;

  @Prop()
  publishedAt?: Date;

  @Prop()
  excerpt?: string;

  @Prop()
  featuredImage?: string;

  @Prop({ type: [Number], default: [] })
  categories: Number[];

  @Prop({ type: [Number], default: [] })
  tags: number[];

  @Prop({ required: true, default: false })
  pinTop: boolean;
  
  @Prop({ 
    type: Number,
    min: 0,
    max: 5,
    required: false,
    default: 0.0
   })
  reviews: number;

  @Prop({ required: true, default: 0 })
  views: number;

  @Prop()
  metaTitle?: string;

  @Prop()
  metaKeyword?: string;

  @Prop()
  metaDescription?: string;

  @Prop({ type: Types.ObjectId, ref: 'User', required: true })
  author: Types.ObjectId;
}

export const PostSchema = SchemaFactory.createForClass(Post);