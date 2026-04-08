import { ApiProperty } from '@nestjs/swagger';
import { IsString, IsOptional, IsNumber } from 'class-validator';

export class ReplyContactDto {
  @IsOptional()
  @IsString()
  id: string;

  @ApiProperty({ example: 'string' })
  @IsString()
  confirmContent: string;

  @IsOptional()
  @IsString()
  confirmBy: string;

  @ApiProperty({  enum: ['pending', 'processed', 'abort'], default: 'pending'})
  @IsOptional()
  @IsString()
  status?: string = 'pending';

  @ApiProperty({ required: true })
  @IsOptional()
  @IsString()
  processDate?: string;

  @IsOptional()
  @IsString()
  lang?: string;
}
