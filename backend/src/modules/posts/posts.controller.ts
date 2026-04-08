import {
    Controller,
    Post,
    Get,
    Put,
    Delete,
    Body,
    Param,
    UseGuards,
    Request,
    Headers,
    Query,
} from '@nestjs/common';
import { PostsService } from './posts.service';
import { CreatePostDto } from './dtos/create.post.dto';
import { JwtAuthGuard } from '../auth/auth.guard';
import {
    ApiTags,
    ApiBearerAuth,
    ApiOperation,
    ApiResponse,
    ApiParam,
    ApiBody,
    ApiHeader,
} from '@nestjs/swagger';
import { Roles } from '../decorator/roles.decorator';
import { Role } from '../auth/roles.enum';
import { RolesGuard } from '../auth/roles.guard';
import { Public } from '../decorator/public.decorator';
import {LanguageService} from '../language/language.service';
import { FilterPostsDto, FilterSiteMapDto } from './dtos/filter.post.dto';

@ApiTags('Posts')
@Controller('posts')
export class PostsController {
    constructor(
        private readonly postService: PostsService,
        private readonly languageService: LanguageService
    ) { }

    @UseGuards(JwtAuthGuard, RolesGuard)
    @Roles(Role.Administrator, Role.Editor, Role.Author)
    @Post()
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Create a new post' })
    @ApiHeader({
            name: 'accept-language',
            description: 'Preferred language (e.g., en, vi, fr)',
            required: false,
            schema: {default: 'en'},
        })
    @ApiBody({ type: CreatePostDto })
    @ApiResponse({ status: 201, description: 'Post created successfully.' })
    create(
        @Body() dto: CreatePostDto, 
        @Request() req: any,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const postDto = { ...dto, lang: languageFinal };
        return this.postService.create(postDto, req.user.userId, languageFinal);
    }

    @Public()
    @Get()
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    @ApiOperation({ summary: 'Get all posts' })
    @ApiResponse({ status: 200, description: 'Returns a list of posts.' })
    findAll(
        @Query() query: FilterPostsDto,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        query.lang = languageFinal;

        // console.debug('Effective language:', query);

        return this.postService.findAll(query, languageFinal);
    }

    @Public()
    @Get('site_map')
    @ApiOperation({ summary: 'Get site map posts' })
    @ApiResponse({ status: 200, description: 'Returns a list of posts.' })
    siteMap(
        @Query() query: FilterSiteMapDto
    ) {
        return this.postService.siteMap(query);
    }

    @Public()
    @Get(':key')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    @ApiOperation({ summary: 'Get a single post by id or slug' })
    @ApiResponse({ status: 200, description: 'Returns the post.' })
    findOne(
        @Param('key') key: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
    const language = acceptLanguage || 'en';
    this.languageService.setLanguage(language);
    const languageFinal = this.languageService.getLanguage();

    return this.postService.findOneById(key, languageFinal);
    }

    @UseGuards(JwtAuthGuard)
    @Put(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Update a post by id or slug' })
    @ApiParam({ name: 'id', type: String })
    @ApiBody({ type: CreatePostDto })
    @ApiResponse({ status: 200, description: 'Post updated successfully.' })
    update(
        @Param('id') id: string, 
        @Body() dto: Partial<CreatePostDto>,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = dto.lang || acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        const postDto = { ...dto, lang: languageFinal };
        return this.postService.update(id, postDto, languageFinal);
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
    @ApiOperation({ summary: 'Pin top a post by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Pin top updated successfully.' })
    pinTop(
        @Param('id') id: string, 
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.postService.pinTop(id, languageFinal);
    }

    @UseGuards(JwtAuthGuard, RolesGuard)
    @Roles(Role.Administrator, Role.Editor)
    @Delete(':id')
    @ApiHeader({
        name: 'accept-language',
        description: 'Preferred language (e.g., en, vi, fr)',
        required: false,
        schema: {default: 'en'},
    })
    @ApiBearerAuth()
    @ApiOperation({ summary: 'Delete a post by id' })
    @ApiParam({ name: 'id', type: String })
    @ApiResponse({ status: 200, description: 'Post deleted successfully.' })
    remove(
        @Param('id') id: string,
        @Headers('accept-language') acceptLanguage?: string
    ) {
        const language = acceptLanguage || 'en';
        this.languageService.setLanguage(language);
        const languageFinal = this.languageService.getLanguage();

        return this.postService.delete(id, languageFinal);
    }
}
