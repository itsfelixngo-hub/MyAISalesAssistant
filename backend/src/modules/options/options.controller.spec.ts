import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { LanguageService } from '../language/language.service';
import { JwtAuthGuard } from '../auth/auth.guard';
import { OptionsController } from './options.controller';
import { OptionsService } from './options.service';

describe('OptionsController', () => {
  let controller: OptionsController;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [OptionsController],
      providers: [
        { provide: OptionsService, useValue: {} },
        {
          provide: LanguageService,
          useValue: { setLanguage: jest.fn(), getLanguage: jest.fn().mockReturnValue('en') },
        },
        { provide: JwtAuthGuard, useValue: { canActivate: jest.fn().mockReturnValue(true) } },
        { provide: ConfigService, useValue: { get: jest.fn() } },
      ],
    }).compile();

    controller = module.get<OptionsController>(OptionsController);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });
});
