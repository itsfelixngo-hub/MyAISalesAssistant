import { ApiProperty, ApiPropertyOptional } from "@nestjs/swagger";
import { IsDateString, IsEnum, IsOptional, IsString, Matches } from "class-validator";
import { SortOrder } from "src/utils/enum.util";
import { PaginateDto } from "src/utils/paginate.dto";
export class FilterContactsDto extends PaginateDto {
    @IsOptional()
    @IsString()
    @ApiProperty({
        description: `Status 'Comma-separated type (e.g., new, pending, processed, abort)`,
        type: String,
        required: false,
        example: "new, processed",
    })
    status?: string;

    @IsOptional()
    @IsString()
    @ApiProperty({
        description: 'Comma-separated program numbers (e.g., 1, 2, ...)',
        type: String,
        required: false,
        example: "1,2",
    })
    @Matches(/^(\d+,)*\d+$/, { message: 'program must be a comma-separated list of numbers' })
    program?: string;

    @IsOptional()
    @IsString()
    @ApiProperty({
        description: 'Comma-separated school numbers (e.g., 1, 2, ...)',
        type: String,
        required: false,
        example: "1,2",
    })
    @Matches(/^(\d+,)*\d+$/, { message: 'school must be a comma-separated list of numbers' })
    school?: string;

    @IsOptional()
    @IsString()
    @ApiProperty({
        type: String,
        required: false
    })
    keyword: string;

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