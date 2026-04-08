import {ApiProperty, ApiPropertyOptional} from '@nestjs/swagger';
import {Type} from 'class-transformer';
import {
    ArrayNotEmpty,
    IsArray,
    IsNotEmpty,
    IsNumber,
    IsOptional,
    IsString,
    Matches,
    ValidateNested
} from 'class-validator';

export class OptionDto {
    @IsString()
    @ApiProperty({
        description: 'The name of the option to retrieve',
        type: String,
        example: 'prefix_option1',
    })
    @IsNotEmpty()
    @Matches(/^[a-zA-Z0-9_]+$/, {
        message: 'Name can only contain letters, numbers, and underscores (_)',
    })
    name: string;

    @IsString()
    @ApiProperty({
        description: 'Content type: text, html, escape html',
        type: String,
        example: "prefix_option1 &lt;h1&gt;Xin chào&lt;/h1&gt;&lt;p&gt;Đây là bài viết rất dài...&lt;/p&gt;",
    })
    @IsNotEmpty()
    value: string;

    @IsNumber()
    @IsOptional()
    @ApiPropertyOptional({
        description: '0: pending, 1: on, 2: off',
        required: false,
        example: 1,
        default: 1
      })
    autoLoad: number;

    @IsOptional()
    @IsString()
    lang?: string;
}

export class OptionsDto {
    @IsArray()
    @ValidateNested({ each: true })
    @Type(() => OptionDto)
    @ApiProperty({ type: [OptionDto], description: 'List of options' })
    options: OptionDto[];
}


export class GetOptionDto {
    @IsString()
    @ApiProperty({
        description: 'The name of the option to retrieve',
        type: String,
        example: 'option1',
    })
    optionName: string;

    @IsOptional()
    @IsString()
    lang?: string;
}

export class GetOptionsDto {
    @IsString()
    @ApiProperty({
        description: 'Comma-separated option names (e.g., option1, option2)',
        type: String,
        example: "option1, option2",
    })
    optionNames: string;

    @IsOptional()
    @IsString()
    lang?: string;
}