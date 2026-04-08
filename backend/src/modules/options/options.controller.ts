import {Body, Controller, Delete, Get, Headers, Param, Post, Put, Query, Req, UseGuards} from '@nestjs/common';
import {GetOptionDto, GetOptionsDto, OptionDto, OptionsDto} from './options.dto';
import {OptionsService} from './options.service';
import {JwtAuthGuard} from '../auth/auth.guard';
import {I18n, I18nContext} from 'nestjs-i18n';
import {LanguageService} from '../language/language.service';
import {ApiBearerAuth, ApiBody, ApiHeader, ApiOperation, ApiQuery} from '@nestjs/swagger';
import { Public } from '../decorator/public.decorator';

@Controller('options')
// @UseGuards(JwtAuthGuard)
export class OptionsController {
    constructor(
        private readonly optionService: OptionsService,
        private readonly languageService: LanguageService
    ) {
    }

    // @Get('test')
    // async getHello(@I18n() i18n: I18nContext, lang: string) {
    //     return i18n.t('errors.CREATE_RECORD_FAIL', { lang: `${lang}` });
    // }
    @Public()
    @Get('')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async getOption(
        @Query() query: GetOptionDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const {optionName, lang} = query;
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();
        query.lang = languageFinal;

        return this.optionService.getOption(optionName, languageFinal);
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Post('')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async create(
        @Body() optionDto: OptionDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = optionDto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const {name, value, lang} = optionDto;
        optionDto.lang = languageFinal;

        return this.optionService.create(optionDto, languageFinal)
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Put('')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async update(
        @Body() optionDto: OptionDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = optionDto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const {name, value, lang} = optionDto;
        optionDto.lang = languageFinal;
        return this.optionService.update(optionDto, languageFinal);
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Delete('')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async delete(
        @Query() query: GetOptionDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const {optionName, lang} = query;
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();
        query.lang = languageFinal;

        return this.optionService.delete(optionName, languageFinal);
    }

    @Public()
    @Get('multi')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async getOptions(
        @Query() query: GetOptionsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const {optionNames, lang} = query;
        const options = optionNames.split(",");
        const language = lang || acceptLanguage || 'en';

        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.optionService.getOptions(options, languageFinal);
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Post('multi')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async createMulti(
        @Body() optionsDto: OptionsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        // Determine language priority
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const finalLanguage = this.languageService.getLanguage();

        // Set the detected language for each option
        optionsDto.options.forEach(option => {
            option.lang = option.lang || finalLanguage; // Use provided lang or fallback
        });

        return this.optionService.createMulti(optionsDto, finalLanguage);
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Put('multi')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async updateMulti(
        @Body() optionsDto: OptionsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        // Determine language priority
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const finalLanguage = this.languageService.getLanguage();

        // Set the detected language for each option
        optionsDto.options.forEach(option => {
            option.lang = option.lang || finalLanguage; // Use provided lang or fallback
        });
        return this.optionService.updateMulti(optionsDto, finalLanguage);
    }

    @ApiBearerAuth()
    @UseGuards(JwtAuthGuard)
    @Delete('multi')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    async deleteMulti(
        @Query() query: GetOptionsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const {optionNames, lang} = query;
        const options = optionNames.split(",");
        const language = lang || acceptLanguage || 'en';

        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.optionService.deleteMulti(options, languageFinal);
    }
}
