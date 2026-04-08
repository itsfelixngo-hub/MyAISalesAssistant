import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { ConfigService } from '@nestjs/config';
import { GlobalExceptionFilter } from './common/error.filter';
import { ValidationPipe } from '@nestjs/common';
import { setupSwagger } from './utils/swagger.util';
import * as bodyParser from 'body-parser';
// import { IoAdapter } from '@nestjs/platform-socket.io';
// import { CustomSocketIoAdapter } from './adapter/custom.socketIo.adapter';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const configService = app.get(ConfigService);
  const port = configService.get<number>('PORT', 3000);
  const serverNameProxy = configService.get<string>('SERVER_NAME_PROXY', 'http://localhost.api');
  // Apply global error filter
  app.useGlobalFilters(new GlobalExceptionFilter());

  // app.useWebSocketAdapter(new CustomSocketIoAdapter(app)); // 👈 KÍCH HOẠT SOCKET.IO
  // app.useWebSocketAdapter(new IoAdapter(app));

  // ✅ Kích hoạt ValidationPipe để tự động kiểm tra DTO
  app.useGlobalPipes(new ValidationPipe({
    // whitelist: true, // ✅ Chỉ nhận các field được định nghĩa trong DTO
    // forbidNonWhitelisted: true, // ✅ Chặn field không mong muốn
    transform: true, // ✅ Chuyển đổi dữ liệu đúng kiểu (string -> number, etc.)
  }));

  // Increase the limit of the request payload to 50MB (adjust as needed)
  app.use(bodyParser.json({ limit: '50mb' }));  // for JSON payload
  app.use(bodyParser.urlencoded({ limit: '50mb', extended: true }));  // for URL-encoded data

  const allowedOriginsRaw = [`http://localhost:${port}`, ...serverNameProxy.split(";")];
  const allowedOrigins = allowedOriginsRaw.filter(Boolean); // remove empty strings

  app.enableCors({
    origin: (origin, callback) => {
      if (!origin) return callback(null, true);

      const isAllowed = allowedOrigins.some((allowed) => {
        if (allowed.includes("*")) {
          // Chuyển chuỗi wildcard thành RegExp
          const regex = new RegExp("^" + allowed.replace(/\*/g, ".*") + "$");
          return regex.test(origin);
        }
        return allowed === origin;
      });

      if (isAllowed) {
        callback(null, true);
      } else {
        callback(new Error("Not allowed by CORS"));
      }
    },
    credentials: true,
  });

  setupSwagger(app);
  await app.listen(port);
  console.log(`Server running on http://localhost:${port}`);
  console.log('NODE_ENV=', process.env.NODE_ENV);
  console.log('BOOT FILE:', __filename);

}
bootstrap();
