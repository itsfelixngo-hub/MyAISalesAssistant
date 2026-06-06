import { Test, TestingModule } from '@nestjs/testing';
import { getModelToken } from '@nestjs/mongoose';
import { I18nService } from 'nestjs-i18n';
import { Faq } from './faq.schema';
import { FaqService } from './faq.service';

describe('FaqService', () => {
  let service: FaqService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        FaqService,
        { provide: getModelToken(Faq.name), useValue: jest.fn() },
        { provide: I18nService, useValue: { translate: jest.fn((key) => key) } },
      ],
    }).compile();

    service = module.get<FaqService>(FaqService);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });
});
