```
samsung_edu/
│── src/
│   ├── modules/                # Chứa các module chính
│   │   ├── users/              
│   │   │   ├── users.module.ts
│   │   │   ├── users.controller.ts
│   │   │   ├── users.service.ts
│   │   │   ├── users.schema.ts
│   │   │   ├── dto/            # Tách DTO riêng để rõ ràng hơn
│   │   │   │   ├── create-user.dto.ts
│   │   │   │   ├── update-user.dto.ts
│   │   │   ├── interfaces/     # Định nghĩa interface rõ ràng hơn
│   │   │   │   ├── user.interface.ts
│   │   ├── posts/              
│   │   │   ├── posts.module.ts
│   │   │   ├── posts.controller.ts
│   │   │   ├── posts.service.ts
│   │   │   ├── posts.schema.ts
│   │   │   ├── dto/
│   │   │   ├── interfaces/
│   │   ├── comments/           
│   │   │   ├── comments.module.ts
│   │   │   ├── comments.controller.ts
│   │   │   ├── comments.service.ts
│   │   │   ├── comments.schema.ts
│   │   │   ├── dto/
│   │   │   ├── interfaces/
│   │   ├── auth/               
│   │   │   ├── auth.module.ts
│   │   │   ├── auth.controller.ts
│   │   │   ├── auth.service.ts
│   │   │   ├── auth.guard.ts
│   │   │   ├── auth.strategy.ts # Thêm strategy để quản lý JWT rõ ràng hơn
│   │   │   ├── dto/
│   ├── common/                 # Middleware, Pipes, Filters chung
│   │   ├── database.module.ts  # Kết nối MongoDB
│   │   ├── error.filter.ts      # Xử lý lỗi chung
│   │   ├── logger.middleware.ts # Middleware log request
│   ├── config/                  # Chứa các file cấu hình chung
│   │   ├── config.service.ts
│   │   ├── env.validation.ts
│   ├── app.controller.ts 
│   ├── app.module.ts 
│   ├── app.service.ts
│   ├── main.ts                  # Khởi động app
│── .env                         # Config môi trường
│── tsconfig.json                 # Cấu hình TypeScript
│── package.json                  # Package manager
│── README.md                     # Hướng dẫn dự án
```

Làm việc với docker compose
# 1. xem log backend
docker logs -f maa_backend_dev

# 2. reload compose riêng backend
docker compose -f docker-compose.dev.yml up -d --force-recreate backend

# 3. rebuild backend
docker compose -f docker-compose.dev.yml up -d --build --force-recreate backend

# 4. reset backend mạnh tay
docker compose -f docker-compose.dev.yml stop backend
docker compose -f docker-compose.dev.yml rm -f backend
rm -rf backend/node_modules
docker compose -f docker-compose.dev.yml up -d --build backend