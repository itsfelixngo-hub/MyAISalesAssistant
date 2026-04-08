import { Injectable } from '@nestjs/common';
import { ExErrorException } from 'src/common/error.filter';
import { UploadStorageDto } from './upload.dto';
import { extname, join } from 'path';
import * as fs from 'fs';

@Injectable()
export class UploadStorageService {
    handleFileUpload(file: Express.Multer.File, uploadFileDto: UploadStorageDto) {
        if (!file) {
            throw new ExErrorException('FILE_NOT_FOUND', 404);
        }

        const uploadPath = file.destination;
        const fileExt = extname(file.originalname);
        const { customFilename } = uploadFileDto;

        // Generate final filename
        let finalFilename = customFilename ? `${customFilename}${fileExt}` : file.filename;
        const tempFilePath = join(uploadPath, file.filename);
        let finalFilePath = join(uploadPath, finalFilename);

        //console.log(`Renaming file from: ${tempFilePath} to ${finalFilePath}`);

        try {
            if (fs.existsSync(finalFilePath)) {
                console.warn(`File ${finalFilename} already exists!`);

                // Rename file to avoid conflict
                finalFilename = `${customFilename || 'file'}_${Date.now()}${fileExt}`;
                finalFilePath = join(uploadPath, finalFilename);
            }

            // Rename the temp file to final filename
            fs.renameSync(tempFilePath, finalFilePath);
            // console.log(`File renamed successfully: ${finalFilename}`);

            return {
                message: 'File uploaded successfully!',
                filename: finalFilename,
                path: finalFilePath.replace('./storage', 'storage'), // Clean up path
                // category: uploadFileDto.category,
            };
        } catch (error) {
            // console.error('File rename error:', error);
            throw new ExErrorException('UPLOAD_FAIL', 201);
        }
    }
}
