import {
  ExceptionFilter,
  Catch,
  ArgumentsHost,
  HttpException,
  HttpStatus,
} from '@nestjs/common';
import { Response } from 'express';

@Catch()
export class GlobalExceptionFilter implements ExceptionFilter {
  catch(exception: unknown, host: ArgumentsHost) {
    const ctx = host.switchToHttp();
    const response = ctx.getResponse<Response>();

    let status: HttpStatus = HttpStatus.INTERNAL_SERVER_ERROR;
    let message = 'Internal server error';
    let errors: any = '';
    let errorCode: string | HttpStatus = status;

    // extra fields (redirect, v.v.)
    let location: string | undefined;
    let slugNew: string | undefined;

    if (exception instanceof HttpException) {
      status = exception.getStatus() as HttpStatus;

      const errorResponse = exception.getResponse();
      const payload = typeof errorResponse === 'string' ? null : (errorResponse as any);

      // ưu tiên lấy errorCode/message/errors từ payload
      if (payload) {
        status = (payload.statusCode ?? status) as HttpStatus;

        errorCode = payload.errorCode ?? status;
        message = payload.message ?? message;
        errors = payload.errors ?? message;

        location = payload.location;
        slugNew = payload.slugNew;
      } else {
        // trường hợp throw new HttpException('msg', status)
        errorCode = status;
        message = errorResponse as string;
        errors = message;
      }
    }

    console.error('Error:', exception);
    console.log('errorResponse raw =', exception instanceof HttpException ? exception.getResponse() : exception);

    response.status(status).json({
      result: false,
      errorCode,
      message,
      errors,
      location,
      slugNew,
      timestamp: new Date().toISOString(),
    });
  }
}

export class ExErrorException extends HttpException {
  constructor(
    errorCode?: string,
    statusCode: HttpStatus = HttpStatus.BAD_REQUEST,
    message?: string,
    errors?: any,
  ) {
    super({ errorCode, statusCode, message, errors }, statusCode);
  }
}

/**
 * ExRedirectException
 */
export class ExRedirectException extends HttpException {
  constructor(
    location: string,
    slugNew?: string,
    errorCode: string = 'FETCH_RECORD_REDIRECT',
    statusCode: HttpStatus = HttpStatus.MOVED_PERMANENTLY,
    message: string = 'Moved Permanently',
    errors: any = 'Moved Permanently',
  ) {
    super(
      {
        errorCode,
        statusCode,
        message,
        errors,
        location,
        slugNew,
      },
      statusCode,
    );
  }
}
