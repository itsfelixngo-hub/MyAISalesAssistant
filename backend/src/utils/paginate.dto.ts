import { ApiProperty } from "@nestjs/swagger";
import { Type } from "class-transformer";
import { IsInt, IsOptional, IsString, Min } from "class-validator";

export class PaginateDto {
    @IsOptional()
    @IsInt()
    @Min(1)
    @ApiProperty({
        description: 'Current page number',
        type: Number,
        required: false,
        example: 1,
        default: 1
    })
    @Type(() => Number)
    page?: number;

    @IsOptional()
    @IsInt()
    @Min(1)
    @ApiProperty({
        description: 'Number posts page',
        type: Number,
        required: false,
        example: 1,
        default: 10
    })
    @Type(() => Number)
    limit?: number;

    @IsOptional()
    @IsString()
    lang?: string;
}