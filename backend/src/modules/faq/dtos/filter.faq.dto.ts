import { ApiProperty, ApiPropertyOptional } from "@nestjs/swagger";
import { Transform } from "class-transformer";
import { IsBoolean, IsDateString, IsEnum, IsOptional, IsString, Matches } from "class-validator";
import { SortOrder } from "src/utils/enum.util";
import { PaginateDto } from "src/utils/paginate.dto";

export class FilterFaqDto extends PaginateDto {
    @IsOptional()
    @IsString()
    @ApiProperty({
        description: 'Comma-separated cat numbers (e.g., 1, 2, ...)',
        type: String,
        required: false,
        example: "1,2",
    })
    @Matches(/^(\d+,)*\d+$/, { message: 'category must be a comma-separated list of numbers' })
    category?: string;

    @IsOptional()
    @IsString()
    @ApiProperty({
        description: `Status 'Comma-separated type (e.g., new, hidden, pending, processed, abort, posted)`,
        type: String,
        required: false,
        example: "new, posted",
    })
    status?: string;

    @IsOptional()
    @IsString()
    @ApiProperty({
        description: `Status 'Comma-separated type (e.g., 67fe0090a4d8ec876322b8df, 67fdfbff10b086e4baffc423)`,
        type: String,
        required: false,
        example: "67fe0090a4d8ec876322b8df, 67fdfbff10b086e4baffc423",
    })
    exclude?: string;

    @IsOptional()
    @IsBoolean()
    @Transform(({ value }) => value === 'true' || value === true)  // Converts string 'true' to boolean
    @ApiProperty({
        type: Boolean,
        required: false,
        default: false,
    })
    reviews?: boolean;

    @IsOptional()
    @IsBoolean()
    @Transform(({ value }) => value === 'true' || value === true)  // Converts string 'true' to boolean
    @ApiProperty({
        type: Boolean,
        required: false,
        default: false,
    })
    views?: boolean;

    @IsOptional()
    @IsBoolean()
    @Transform(({ value }) => value === 'true' || value === true)  // Converts string 'true' to boolean
    @ApiProperty({
        type: Boolean,
        required: false,
        default: false,
    })
    pinTop?: boolean;

    @IsOptional()
    @IsString()
    @ApiProperty({
        type: String,
        required: false,
        example: "string",
    })
    keyword?: string;

    @ApiPropertyOptional({ type: String, format: 'date-time' })
    @IsOptional()
    @IsDateString()
    startDate?: string;

    @ApiPropertyOptional({ type: String, format: 'date-time' })
    @IsOptional()
    @IsDateString()
    endDate?: string;

    @ApiPropertyOptional({ enum: SortOrder, default: SortOrder.DESC })
    @IsOptional()
    @IsEnum(SortOrder)
    sort?: SortOrder;
}