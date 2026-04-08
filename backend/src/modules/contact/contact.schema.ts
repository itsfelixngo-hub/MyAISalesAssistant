import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Types } from 'mongoose';
import { langSchema } from '../schemas/lang.schema';

@Schema({ timestamps: true })
export class Contact extends langSchema {

    @Prop({ required: true })
    senderName: string;

    @Prop({ required: true })
    senderMail: string;

    @Prop({ required: true })
    senderTel: string;

    @Prop({ required: false, type: [Number] })
    senderChooseProgram: number[];
    
    @Prop({ required: false, type: [Number] })
    senderChooseSchool: number[];

    @Prop({ type: String, default: null })
    senderMessage: string;

    @Prop({ required: true, enum: ['new', 'pending', 'processed', 'abort'], default: 'new' })
    status: string;

    @Prop({ type: String, default: null })
    processDate: string;

    @Prop({ type: Types.ObjectId, ref: 'User', default: null })
    approveBy: Types.ObjectId;

    @Prop({ type: Types.ObjectId, ref: 'User', default: null })
    confirmBy: Types.ObjectId;

    @Prop({ type: String, default: null })
    confirmContent: string;
}


export const contactSchema = SchemaFactory.createForClass(Contact);
