import { Controller, Get, Headers, Post, Body } from '@nestjs/common';
import { LanguageService } from './language.service';
import { ApiHeader, ApiOperation, ApiTags } from '@nestjs/swagger';

@Controller('language')
export class LanguageController {
  constructor(private readonly languageService: LanguageService) { }

  // Automatically sets language from Accept-Language header
  @Post('set')
  @ApiOperation({ summary: 'Set the language for the user session' }) // ✅ Describes the API action
  @ApiHeader({
    name: 'accept-language',
    description: 'Preferred language (vi,en;q=0.8)',
    required: false, // Optional header
    schema: { default: 'en' },
  })
  async setLanguage(
    @Body('lang') lang?: string,
    @Headers('accept-language') acceptLanguage?: string
  ) {
    // Determine final language
    const language = lang || acceptLanguage || 'en';

    // Store the language
    this.languageService.setLanguage(language);

    console.log('Final Language:', language);

    return { message: `Language set to ${language}` };
  }
}
