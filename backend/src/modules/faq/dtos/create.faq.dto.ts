import { ApiHideProperty, ApiProperty, OmitType } from "@nestjs/swagger";
import { Transform } from "class-transformer";
import { IsArray, IsBoolean, IsEnum, IsNotEmpty, IsNumber, IsOptional, IsString, Max, Min } from "class-validator";

export class CreateFaqDto {
    @ApiProperty({ example: 'string' })
    @IsNotEmpty()
    @IsString()
    question: string;

    @ApiProperty({ example: 'string', default: null })
    @IsString()
    answer: string;

    @ApiProperty({ default: false })
    @IsOptional()
    @IsBoolean()
    pinTop: boolean;

    @IsOptional()
    @IsNumber()
    @Min(0)
    @Max(5)
    @Transform(({ value }) => {
        if (value === undefined || value === null || value === '') return 0.0;
        const parsed = parseFloat(value);
        return isNaN(parsed) ? 0.0 : parsed;
    })
    reviews?: number;

    @ApiProperty({ enum: ['new', 'hidden', 'pending', 'processed', 'abort', 'posted'], default: 'new' })
    @IsOptional()
    @IsEnum(['new', 'hidden', 'pending', 'processed', 'abort', 'posted'])
    status?: string;

    @ApiProperty({ required: false, example: [1, 3] })
    @IsOptional()
    @IsArray()
    categories?: number[];

    @IsOptional()
    @IsString()
    answerby?: string;

    @IsOptional()
    @IsString()
    author?: string;

    @IsOptional()
    @IsString()
    lang?: string;
}

export class CreateQuestionDto extends OmitType(CreateFaqDto, ['answer', 'status', 'pinTop'] as const) { }
