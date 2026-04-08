import { ApiProperty } from "@nestjs/swagger";
import { IsOptional, IsString } from "class-validator";

export class UpdateContactDto {
    @ApiProperty({ required: true })
    @IsString()
    senderName: string;

    @ApiProperty({ required: true })
    senderMail: string;

    @ApiProperty({ required: true })
    senderTel: string;

    @ApiProperty({ required: true, type: [Number] })
    senderChooseProgram: Number;

    @ApiProperty({ required: true, type: [Number] })
    senderChooseSchool: Number;

    @ApiProperty({ required: true, type: String })
    senderMessage: string;

    @ApiProperty({ required: true, type: Number, default: 1 })
    status: number;

    @IsOptional()
    approveBy: object;

    @IsOptional()
    complaineBy: object;

    @IsOptional()
    @IsString()
    lang?: string;
}