import { Test, TestingModule } from '@nestjs/testing';
import { getModelToken } from '@nestjs/mongoose';
import { I18nService } from 'nestjs-i18n';
import { Option } from './option.schema';
import { OptionsService } from './options.service';

describe('OptionsService', () => {
  let service: OptionsService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OptionsService,
        { provide: getModelToken(Option.name), useValue: jest.fn() },
        { provide: I18nService, useValue: { translate: jest.fn((key) => key) } },
      ],
    }).compile();

    service = module.get<OptionsService>(OptionsService);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });
});
