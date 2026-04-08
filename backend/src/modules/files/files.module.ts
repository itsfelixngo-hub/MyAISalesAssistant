import { Module } from '@nestjs/common';
import { AwsS3Service } from './aws-s3.service';
import { ConfigService } from 'aws-sdk';
import { FileController } from './file.controller';
import { UploadStorageService } from './upload-storage.service';

@Module({
  imports: [ConfigService],
  providers: [AwsS3Service, UploadStorageService],
  controllers: [FileController]
})
export class FilesModule {}
