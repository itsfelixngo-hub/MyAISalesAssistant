import { ApiProperty } from '@nestjs/swagger';
import { IsOptional, IsString, Matches } from 'class-validator';

export class UploadStorageDto {
  @ApiProperty({ 
    type: 'string', 
    format: 'binary', 
    description: 'File to upload' 
  })
  file: any;

  // @ApiProperty({ example: 'profile_picture', description: 'File category', required: false })
  // @IsOptional()
  // @IsString()
  // category?: string;

  @ApiProperty({ example: 'custom_filename', description: 'Custom filename (without extension)', required: false })
  @IsOptional()
  @IsString()
  @Matches(/^[a-zA-Z0-9_-]+$/, {
    message: 'Filename must only contain letters, numbers, hyphens, or underscores',
  })
  customFilename?: string;
}
