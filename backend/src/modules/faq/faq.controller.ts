import { Body, Controller, Delete, Get, Headers, Param, Post, Put, Query, Request, UseGuards } from '@nestjs/common';
import { LanguageService } from '../language/language.service';
import { Public } from '../decorator/public.decorator';
import { ApiBearerAuth, ApiBody, ApiHeader, ApiOperation, ApiParam, ApiResponse } from '@nestjs/swagger';
import { FaqService } from './faq.service';
import { CreateFaqDto, CreateQuestionDto } from './dtos/create.faq.dto';
import { FilterFaqDto } from './dtos/filter.faq.dto';
import { JwtAuthGuard } from '../auth/auth.guard';
import { Roles } from '../decorator/roles.decorator';
import { Role } from '../auth/roles.enum';
import { RolesGuard } from '../auth/roles.guard';

@Controller('faq')
export class FaqController {
    constructor(
        private readonly languageService: LanguageService,
        private readonly faqService: FaqService,
    ) { }

    @Public()
    @Post('/question')
    @ApiOperation({ summary: 'Create a question' })
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBody({ type: CreateQuestionDto })
    @ApiResponse({ status: 201, description: 'Faq created successfully.' })
    createQuestion(
        @Body() dto: CreateQuestionDto,
        @Request() req: any,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const postDto = { ...dto, lang: languageFinal };
        return this.faqService.createQuestion(postDto, req?.userId, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Post()
    @ApiOperation({ summary: 'Create a new faq' })
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBearerAuth()
    @ApiBody({ type: CreateFaqDto })
    @ApiResponse({ status: 201, description: 'Faq created successfully.' })
    create(
        @Body() dto: CreateFaqDto,
        @Request() req: any,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const postDto = { ...dto, lang: languageFinal };
        return this.faqService.create(postDto, req?.user.userId, languageFinal);
    }


    @Public()
    @Get()
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiOperation({ summary: 'Get all faqs' })
    @ApiResponse({ status: 200, description: 'Returns a list of posts.' })
    findAll(
        @Query() query: FilterFaqDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        query.lang = languageFinal;
        // console.debug('Effective language:', query);
        return this.faqService.findAll(query, languageFinal);
    }

    @Public()
    @Get(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiOperation({ summary: 'Get a single faq by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Returns the faq.' })
    findOne(
        @Param('id') id: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();
        return this.faqService.findOneById(id, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Put(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Update a faq by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiBody({ type: CreateFaqDto })
    @ApiResponse({ status: 200, description: 'Post updated successfully.' })
    update(
        @Param('id') id: string,
        @Request() req: any,
        @Body() dto: Partial<CreateFaqDto>,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const faqDto = { ...dto, lang: languageFinal };
        return this.faqService.update(id, faqDto, req?.user.userId, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Put('pin/:id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Pin top a faq by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Pin top updated successfully.' })
    pinTop(
        @Param('id') id: string, 
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.faqService.pinTop(id, languageFinal);
    }

    @UseGuards(JwtAuthGuard, RolesGuard)
    @Roles(Role.Administrator, Role.Editor)
    @Delete(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Delete a faq by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Faq deleted successfully.' })
    remove(
        @Param('id') id: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.faqService.delete(id, languageFinal);
    }
}
