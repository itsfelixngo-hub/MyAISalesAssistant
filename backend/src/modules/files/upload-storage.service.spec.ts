import { Test, TestingModule } from '@nestjs/testing';
import { UploadStorageService } from './upload-storage.service';

describe('UploadStorageService', () => {
  let service: UploadStorageService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [UploadStorageService],
    }).compile();

    service = module.get<UploadStorageService>(UploadStorageService);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });
});
