import { IsEmail, IsString } from 'class-validator';
import {ApiProperty} from "@nestjs/swagger";

export class LoginDto {
  @IsEmail()
  @ApiProperty({
    type: String,
    example: 'admin@example.com',
  })
  email: string;

  @IsString()
  @ApiProperty({
    type: String,
    example: 'admin123',
  })
  password: string;
}
