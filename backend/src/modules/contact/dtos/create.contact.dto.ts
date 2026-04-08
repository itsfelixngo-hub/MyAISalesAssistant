import { ApiProperty } from "@nestjs/swagger";
import { IsOptional, IsString } from "class-validator";

export class CreateContactDto {
    @ApiProperty({ required: true})
    @IsString()
    senderName: string;

    @ApiProperty({ required: true})
    senderMail: string;

    @ApiProperty({ required: true})
    senderTel: string;

    @ApiProperty({ required: false, type:[Number], example:[1,2] })
    senderChooseProgram: Number;

    @ApiProperty({ required: false, type: [Number], example:[1,2] })
    senderChooseSchool: Number;

    @ApiProperty({ type: String })
    senderMessage: string;

    @IsOptional()
    @IsString()
    lang?: string;
}