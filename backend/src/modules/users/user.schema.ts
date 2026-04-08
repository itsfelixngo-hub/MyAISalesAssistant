import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document, Types } from 'mongoose';
import { Role } from '../auth/roles.enum';

@Schema({ timestamps: true })
export class User extends Document {
  @Prop({
    required: false, unique: true,
    default: function () {
      const [username, domain] = this.email.split("@");
      return username;
    }
  })
  userName: string;

  @Prop({ required: true, unique: true })
  email: string;

  @Prop({ required: true })
  password: string;

  @Prop({ required: true, default: Role.Member })
  role: number;

  @Prop({ required: false, default: null })
  niceName: string;

  @Prop({ required: false, default: null })
  displayName: string;

  @Prop({ required: false, default: '#' })
  avatar: string;

  @Prop({ required: false, default: 0 })
  status: number;

}
export const UserSchema = SchemaFactory.createForClass(User);
