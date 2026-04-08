import { Controller, Post, UploadedFile, UseInterceptors, BadRequestException, Body, UseGuards } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { AwsS3Service } from './aws-s3.service';
import { ApiBearerAuth, ApiBody, ApiConsumes, ApiOperation, ApiResponse } from '@nestjs/swagger';
import { ExErrorException } from 'src/common/error.filter';
import { UploadStorageDto } from './upload.dto';
import { diskStorage } from 'multer';
import * as fs from 'fs';
import { extname } from 'path';
import { UploadStorageService } from './upload-storage.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('upload')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
export class FileController {
  constructor(
    private readonly uploadService: AwsS3Service,
    private readonly uploadStorage: UploadStorageService
  ) {}

  @Post('s3/storage')
  @ApiOperation({ summary: 'Upload a file to AWS S3' })
  @ApiConsumes('multipart/form-data')
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        file: {
          type: 'string',
          format: 'binary',
        },
      },
    },
  })
  @ApiResponse({ status: 201, description: 'File uploaded successfully' })
  @ApiResponse({ status: 400, description: 'Invalid file format or size' })
  @UseInterceptors(
    FileInterceptor('file', {
      limits: { fileSize: 10 * 1024 * 1024 }, // Giới hạn file 10MB
      fileFilter: (req, file, callback) => {
        const allowedMimes = [
          'image/png', 'image/jpeg', 'image/jpg', // Ảnh
          'application/pdf', 'application/msword', // PDF, Word
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel
          'video/mp4' // Video
        ];
  
        if (!allowedMimes.includes(file.mimetype)) {
          return callback(new BadRequestException('File type not allowed!'), false);
        }
        callback(null, true);
      },
    }),
  )
  async uploadFile(@UploadedFile() file: Express.Multer.File) {
    try {
      const url = await this.uploadService.uploadFile(file);
      return { url };
    } catch (error) {
      throw new ExErrorException('UPLOAD_S3_FAIL', 500)
    }
  }


  @Post('storage')
  @ApiConsumes('multipart/form-data')
  @ApiBody({
    description: 'Upload file to local storage',
    schema: {
      type: 'object',
      properties: {
        file: { type: 'string', format: 'binary' },
        customFilename: { type: 'string', example: 'my_file' },
      },
    },
  })
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: (req, file, callback) => {
          const now = new Date();
          const uploadPath = `./storage/${now.getFullYear()}/${now.getMonth() + 1}/${now.getDate()}`;

          // Ensure the directory exists
          fs.mkdirSync(uploadPath, { recursive: true });

          callback(null, uploadPath);
        },
        filename: (req, file, callback) => {
          const { customFilename } = req.body;
          const fileExt = extname(file.originalname);
          const finalFilename = customFilename ? `${customFilename}${fileExt}` : `file_${Date.now()}${fileExt}`;

          callback(null, finalFilename);
        }
      }),
    }),
  )
  async uploadFileLocal(
    @UploadedFile() file: Express.Multer.File,
    @Body() uploadFileDto: UploadStorageDto
  ) {
    if (!file) {
      throw new ExErrorException('FILE_NOT_EXIST', 404);
    }
    return this.uploadStorage.handleFileUpload(file, uploadFileDto);
  }
}
