import { IsEmail, IsNumber, IsOptional, IsString, MinLength } from 'class-validator';
import {ApiProperty, ApiPropertyOptional} from "@nestjs/swagger";

export class RegisterDto {
  @IsString()
  @ApiProperty({
    type: String,
    example: 'username_example',
  })
  userName?: string;

  @IsOptional()
  @IsString()
  @ApiProperty({
    type: String,
    example: 'string',
  })
  avatar?: string;
  
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
  @MinLength(6)
  password: string;

  @IsOptional()
  @IsNumber()
  @ApiPropertyOptional({
    example: 1,
    description: `Optional role of the user (Administrator = 100, // The highest level of permission
  Editor = 10,         // Can manage content
  Author = 9,          // Can publish their own posts
  Contributor = 8,     // Can write drafts, no publishing rights
  Viewer = 7,          // Can read and comment
  Subscriber = 6,      // Follows updates
  Member = 1           // Forum member)`,
  })
  role?: number;

  @IsOptional()
  @IsNumber()
  @ApiProperty({
    example: 1,
  })
  status?: number;

  @IsString()
  @ApiProperty({
    type: String,
    example: 'nice_example',
  })
  niceName?: string;

  @IsString()
  @ApiProperty({
    type: String,
    example: 'user display name',
  })
  displayName?: string;

  @IsString()
  @IsOptional()
  createBy?: string;
}