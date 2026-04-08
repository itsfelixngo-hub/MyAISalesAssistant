# ======== STAGE 1: BUILD =========
FROM node:20.12.2-alpine AS builder

WORKDIR /app

# Cài dependencies để build
COPY package*.json ./
RUN npm install

# Copy toàn bộ source và build
COPY . .
RUN npm run build

# Kiểm tra có file output build không
RUN test -f dist/main.js || (echo "❌ dist/main.js not found" && exit 1)
RUN test -d i18n/locales || (echo "❌ i18n/locales folder missing" && exit 1)


# ======== STAGE 2: PRODUCTION =========
FROM node:20.12.2-alpine

WORKDIR /app
RUN mkdir -p /app/logs/requests \
    && chown -R 1000:1000 /app/logs \
    && chown -R 1000:1000 /app/logs/requests

RUN mkdir -p /app/data/backup \
    && chown -R 1000:1000 /app/data/backup
    
# Cài production dependencies
COPY package*.json ./
RUN npm install --only=production
RUN npm install -g migrate-mongo
# Copy build + i18n từ stage builder
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/i18n ./i18n

COPY --from=builder /app/migrate-mongo-config.js ./migrate-mongo-config.js
COPY --from=builder /app/migrations ./migrations

EXPOSE 3000

CMD ["node", "dist/main.js"]
