import { IsArray, IsBoolean, IsEnum, IsNotEmpty, IsOptional, IsString } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class CreatePostDto {
  @ApiProperty({ example: 'My First Post' })
  @IsNotEmpty()
  @IsString()
  title: string;

  @ApiProperty({ example: 'my-first-post' })
  @IsOptional()
  @IsString()
  slug: string;

  @ApiProperty({ example: 'my-first-post' })
  @IsOptional()
  @IsString()
  slugOld: string;

  @ApiProperty({ example: '<p>Hello content</p>' })
  @IsNotEmpty()
  @IsString()
  content: string;

  @ApiProperty({ enum: ['new', 'hidden', 'pending', 'processed', 'abort', 'posted', 'scheduled'], default: 'new' })
  @IsOptional()
  @IsEnum(['new', 'hidden', 'pending', 'processed', 'abort', 'posted', 'scheduled'])
  status?: string;

  @ApiProperty({ default: false })
  @IsOptional()
  @IsBoolean()
  pinTop: boolean;

  @ApiProperty({ enum: ['top_university', 'list_of_university', 'majors', 'topik_exams', 'post', 'page', 'news', 'fee', 'scholarship', 'visa', 'guide', 'knowledge'], default: 'post' })
  @IsOptional()
  @IsEnum(['top_university', 'list_of_university', 'majors', 'topik_exams', 'post', 'page', 'news', 'fee', 'scholarship', 'visa', 'guide', 'knowledge'])
  type?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  excerpt?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  featuredImage?: string;

  @ApiProperty({ required: false, example: [1, 3] })
  @IsOptional()
  @IsArray()
  categories?: number[];

  @ApiProperty({ required: false, example: [1, 3] })
  @IsOptional()
  @IsArray()
  tags?: number[];

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  metaTitle?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  metaKeyword?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  metaDescription?: string;

  @IsOptional()
  @IsString()
  lang?: string;
}
