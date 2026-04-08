import { Prop, Schema, SchemaFactory } from "@nestjs/mongoose";
import { Types } from 'mongoose';
import { langSchema } from "../schemas/lang.schema";

@Schema({ timestamps: true })
export class Faq extends langSchema {
    @Prop({ required: true })
    question: string;

    @Prop({ required: false })
    answer: string;

    @Prop({ required: true, enum: ['new', 'hidden', 'pending', 'processed', 'abort', 'posted'], default: 'new' })
    status: string;

    @Prop({ type: [Number], default: [] })
    categories: Number[];

    @Prop({ type: Types.ObjectId, ref: 'User', required: false })
    author: Types.ObjectId;

    @Prop({ type: Types.ObjectId, ref: 'User', required: false })
    answerby: Types.ObjectId;

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

    @Prop({ required: true, default: false })
    pinTop: Boolean;
}
export const FaqSchema = SchemaFactory.createForClass(Faq);