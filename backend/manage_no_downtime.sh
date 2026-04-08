#!/bin/bash

# === MÀU ===
GREEN="\033[0;32m"
CYAN="\033[0;36m"
RED="\033[0;31m"
NC="\033[0m"

# === TÊN CONTAINER ===
NEST_CONTAINER="nestjs_pro_samsung_edu"
MONGO_CONTAINER="mongo_pro_samsung_edu"

# # Khai báo biến backup database
# DATABASE="dbsamsung_edu"
# TIMESTAMP=$(date +%F_%H-%M-%S)
# OUT_DIR="/data/backup/${DATABASE}-${TIMESTAMP}"

# === NHẬP THƯ MỤC DỰ ÁN ===
read -rp "📁 Nhập đường dẫn thư mục dự án chứa docker-compose.yml: " PROJECT_DIR
if [[ ! -f "$PROJECT_DIR/docker-compose.yml" ]]; then
  echo -e "${RED}❌ Không tìm thấy docker-compose.yml trong $PROJECT_DIR${NC}"
  exit 1
fi

function menu() {
  echo -e "${CYAN}"
  echo "╔════════════════════════════════════════════════════════╗"
  echo "║     🎯 Quản lý container theo tên riêng               ║"
  echo "╠════════════════════════════════════════════════════════╣"
  echo "║ 1. Khởi động lại NestJS + Mongo                       ║"
  echo "║ 2. Xem logs NestJS                                    ║"
  echo "║ 3. Xem logs MongoDB                                   ║"
  echo "║ 4. Dừng container NestJS + MongoDB                    ║"
  echo "║ 5. Xóa container (không xóa volume)                   ║"
  echo "║ 6. Xóa container + dữ liệu MongoDB (volume)           ║"
  echo "║ 7. Thoát                                              ║"
  echo "║ 8. 🛠️  Triển khai lại (no downtime)                   ║"
  echo "╚════════════════════════════════════════════════════════╝"
  echo -e "${NC}"
}

while true; do
  menu
  read -p "🔢 Chọn tùy chọn (1-8): " choice

  case $choice in
    1)
      echo -e "${GREEN}🔁 Restart container...${NC}"
      docker restart "$MONGO_CONTAINER"
      docker restart "$NEST_CONTAINER"
      ;;
    2)
      echo -e "${GREEN}📜 Logs NestJS ($NEST_CONTAINER)...${NC}"
      docker logs -f "$NEST_CONTAINER"
      ;;
    3)
      echo -e "${GREEN}📜 Logs MongoDB ($MONGO_CONTAINER)...${NC}"
      docker logs -f "$MONGO_CONTAINER"
      ;;
    4)
      echo -e "${RED}🛑 Dừng container...${NC}"
      docker stop "$NEST_CONTAINER"
      docker stop "$MONGO_CONTAINER"
      ;;
    5)
      echo -e "${RED}❌ Xóa container (không ảnh hưởng volume/image)...${NC}"
      docker stop "$NEST_CONTAINER" && docker rm -f "$NEST_CONTAINER"
      docker stop "$MONGO_CONTAINER" && docker rm -f "$MONGO_CONTAINER"
      ;;
    6)
      echo -e "${RED}❌ XÓA TOÀN BỘ: Container + Volume dữ liệu MongoDB${NC}"
      read -p "⚠️ Bạn chắc chắn muốn xóa dữ liệu MongoDB? (y/N): " confirm
      if [[ $confirm == [yY] ]]; then
        docker stop "$NEST_CONTAINER" "$MONGO_CONTAINER"
        docker volume rm mongo_pro_data_samsung_edu
        echo -e "${GREEN}🧹 Đã xóa container + volume MongoDB.${NC}"
      else
        echo -e "${CYAN}❎ Đã hủy xóa.${NC}"
      fi
      ;;
    7)
      echo "👋 Tạm biệt!"
      exit 0
      ;;
    8)
      echo -e "${GREEN}📦 Đang triển khai lại ứng dụng (không stop container)...${NC}"
      cd "$PROJECT_DIR" || { echo -e "${RED}❌ Không thể vào thư mục dự án!${NC}"; exit 1; }

      DEPLOY_LOG="logs/deploy-$(date +%F_%H-%M-%S).log"
      mkdir -p logs
      echo "📅 [$(date)] BẮT ĐẦU TRIỂN KHAI" | tee -a "$DEPLOY_LOG"

      # ✅ Quan trọng: để pipeline (| tee) trả exit code đúng
      set -o pipefail

      echo -e "${CYAN}🔨 Build Docker image...${NC}" | tee -a "$DEPLOY_LOG"
      docker compose build 2>&1 | tee -a "$DEPLOY_LOG"
      BUILD_EXIT=${PIPESTATUS[0]}

      if [[ $BUILD_EXIT -ne 0 ]]; then
        echo -e "${RED}❌ Build FAILED (exit=$BUILD_EXIT). DỪNG TRIỂN KHAI để không ảnh hưởng container đang chạy.${NC}" | tee -a "$DEPLOY_LOG"
        docker compose ps | tee -a "$DEPLOY_LOG"
        echo -e "${CYAN}↩️ Quay lại menu...${NC}"
        continue
      fi

      echo -e "${CYAN}🚀 Khởi động container (apply image mới nếu có)...${NC}" | tee -a "$DEPLOY_LOG"
      docker compose up -d --remove-orphans 2>&1 | tee -a "$DEPLOY_LOG"
      UP_EXIT=${PIPESTATUS[0]}

      if [[ $UP_EXIT -ne 0 ]]; then
        echo -e "${RED}❌ docker compose up FAILED (exit=$UP_EXIT). DỪNG để tránh migrate nhầm.${NC}" | tee -a "$DEPLOY_LOG"
        docker compose ps | tee -a "$DEPLOY_LOG"
        echo -e "${CYAN}↩️ Quay lại menu...${NC}"
        continue
      fi

      echo -e "${CYAN}⏳ Chờ NestJS sẵn sàng để chạy migrate...${NC}" | tee -a "$DEPLOY_LOG"
      READY_TIMEOUT=120
      START_TS=$(date +%s)
      READY_FAIL=0

      until docker exec "$NEST_CONTAINER" npm run migrate:status > /dev/null 2>&1; do
        sleep 1
        NOW=$(date +%s)
        if (( NOW - START_TS > READY_TIMEOUT )); then
          echo -e "${RED}❌ Timeout (${READY_TIMEOUT}s): NestJS chưa sẵn sàng. DỪNG để tránh migrate nhầm.${NC}" | tee -a "$DEPLOY_LOG"
          docker logs --since 30s "$NEST_CONTAINER" | tee -a "$DEPLOY_LOG"
          READY_FAIL=1
          break
        fi
        printf "." | tee -a "$DEPLOY_LOG"
      done

      if [[ $READY_FAIL -eq 1 ]]; then
        echo -e "${CYAN}↩️ Quay lại menu...${NC}"
        continue
      fi

      echo -e "\n${GREEN}✅ Sẵn sàng, migrate...${NC}" | tee -a "$DEPLOY_LOG"
      docker exec "$NEST_CONTAINER" npm run migrate:up 2>&1 | tee -a "$DEPLOY_LOG"
      MIGRATE_EXIT=${PIPESTATUS[0]}

      if [[ $MIGRATE_EXIT -ne 0 ]]; then
        echo -e "${RED}❌ migrate:up FAILED (exit=$MIGRATE_EXIT). DỪNG bước cleanup để giữ nguyên trạng dễ debug.${NC}" | tee -a "$DEPLOY_LOG"
        echo -e "${CYAN}↩️ Quay lại menu...${NC}"
        continue
      fi

      echo -e "${CYAN}🧹 Dọn image cũ riêng của dự án...${NC}" | tee -a "$DEPLOY_LOG"
      bash clean_project_images.sh 2>&1 | tee -a "$DEPLOY_LOG"
      CLEAN_EXIT=${PIPESTATUS[0]}
      if [[ $CLEAN_EXIT -ne 0 ]]; then
        echo -e "${RED}⚠️ clean_project_images.sh lỗi (exit=$CLEAN_EXIT) - bỏ qua dangling cleanup.${NC}" | tee -a "$DEPLOY_LOG"
      fi

      echo -e "${CYAN}🧹 Dọn image dangling...${NC}" | tee -a "$DEPLOY_LOG"
      docker images --filter "dangling=true" -q | xargs -r docker rmi -f 2>&1 | tee -a "$DEPLOY_LOG"

      echo -e "${CYAN}📋 Trạng thái container của dự án:${NC}" | tee -a "$DEPLOY_LOG"
      docker compose ps | tee -a "$DEPLOY_LOG"

      echo -e "${CYAN}📋 Danh sách images của dự án:${NC}" | tee -a "$DEPLOY_LOG"
      docker inspect --format='{{.Config.Image}}' "$NEST_CONTAINER" | tee -a "$DEPLOY_LOG"
      docker inspect --format='{{.Config.Image}}' "$MONGO_CONTAINER" | tee -a "$DEPLOY_LOG"

      echo -e "${CYAN}📋 Danh sách images trong cả hệ thống:${NC}" | tee -a "$DEPLOY_LOG"
      docker images | tee -a "$DEPLOY_LOG"

      echo -e "${CYAN}🕵️‍♂️ Logs NestJS gần nhất:${NC}" | tee -a "$DEPLOY_LOG"
      docker logs --since 5s "$NEST_CONTAINER" | tee -a "$DEPLOY_LOG"

      echo -e "${CYAN}🕵️‍♂️ Logs MongoDB gần nhất:${NC}" | tee -a "$DEPLOY_LOG"
      docker logs --since 5s "$MONGO_CONTAINER" | tee -a "$DEPLOY_LOG"

      echo -e "${GREEN}✅ Hoàn tất triển khai lúc $(date)${NC}" | tee -a "$DEPLOY_LOG"
      ;;

  esac
done
