import { Body, Controller, Delete, Get, Headers, Param, Post, Put, Query, Request, UseGuards } from '@nestjs/common';
import { Public } from '../decorator/public.decorator';
import { ApiBearerAuth, ApiBody, ApiHeader, ApiOperation, ApiParam, ApiResponse } from '@nestjs/swagger';
import { CreateContactDto } from './dtos/create.contact.dto';
import { LanguageService } from '../language/language.service';
import { ContactService } from './contact.service';
import { FilterContactsDto } from './dtos/filter.contact.dto';
import { JwtAuthGuard } from '../auth/auth.guard';
import { ReplyContactDto } from './dtos/reply.contact.dto';
import { Roles } from '../decorator/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { Role } from '../auth/roles.enum';

@Controller('contact')
export class ContactController {
    constructor(
        private readonly contactService: ContactService,
        private readonly languageService: LanguageService
    ) { }

    @Public()
    @Post()
    @ApiOperation({ summary: 'Create a new contact' })
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBody({ type: CreateContactDto })
    @ApiResponse({ status: 201, description: 'Contact created successfully.' })
    async create(
        @Body() dto: CreateContactDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const contactDto = { ...dto, lang: languageFinal };
        return this.contactService.create(contactDto, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Get()
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiOperation({ summary: 'Get all contacts' })
    @ApiResponse({ status: 200, description: 'Returns a list of contacts.' })
    @ApiBearerAuth()
    findAll(
        @Query() query: FilterContactsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        query.lang = languageFinal;

        // console.debug('Effective language:', query);

        return this.contactService.findAll(query, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Get(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiOperation({ summary: 'Get a single contact by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Returns the contact.' })
    @ApiBearerAuth()
    findOne(
        @Param('id') id: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();
        return this.contactService.findOnebyId(id, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Put('approve/:id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBearerAuth()
    approve(
        @Param('id') id: string,
        @Request() req: any,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();
        return this.contactService.approve(id, req.user.userId, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Put('reply/:id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: { default: 'en' },
    })
    @ApiBearerAuth()
    reply(
        @Request() req: any,
        @Param('id') id: string,
        @Body() dto: ReplyContactDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const replyDto = { ...dto, confirmBy: req.user.userId, lang: languageFinal, id };

        return this.contactService.reply(replyDto, languageFinal);
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
    @ApiOperation({ summary: 'Delete a contact by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Contact deleted successfully.' })
    remove(
        @Param('id') id: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.contactService.delete(id, languageFinal);
    }
}
