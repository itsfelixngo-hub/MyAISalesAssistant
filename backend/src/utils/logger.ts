import * as path from 'path';
import * as fs from 'fs';
import { createLogger, format, transports } from 'winston';
import 'winston-daily-rotate-file';

const logDirectory = path.resolve(__dirname, '../../logs/requests');

// Đảm bảo thư mục tồn tại
if (!fs.existsSync(logDirectory)) {
  fs.mkdirSync(logDirectory, { recursive: true });
}

const dailyRotateFileTransport = new transports.DailyRotateFile({
  filename: path.join(logDirectory, 'suspicious-requests-%DATE%.csv'),
  datePattern: 'YYYY-MM-DD',
  zippedArchive: false,
  maxFiles: '7d',
  level: 'info',
  format: format.combine(
    format.printf((info) => {
      return typeof info.message === 'string' ? info.message : JSON.stringify(info.message);
    })
  ),
});

const logger = createLogger({
  transports: [
    dailyRotateFileTransport,
    new transports.Console({
      format: format.combine(
        format.colorize(),
        format.simple()
      ),
    }),
  ],
});

export default logger;
