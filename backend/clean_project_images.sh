#!/bin/bash

mkdir -p ./logs
LOG_FILE="./logs/image-clean-$(date +%F_%H-%M-%S).log"
: > "$LOG_FILE"

echo "🧹 [$(date)] DỌN IMAGE samsung_edu-nestjs:*" >> "$LOG_FILE"

docker images --format '{{.Repository}}:{{.Tag}} {{.ID}}' \
  | grep '^samsung_edu-nestjs:' \
  | while read -r line; do
    IMAGE_TAG=$(echo "$line" | awk '{print $1}')
    IMAGE_ID=$(echo "$line" | awk '{print $2}')

    CONTAINERS=$(docker ps -a --filter ancestor="$IMAGE_ID" --format "{{.ID}} ({{.Names}})")

    if [[ -z "$CONTAINERS" ]]; then
      docker rmi -f "$IMAGE_ID" >> "$LOG_FILE" 2>&1
      echo "✅ Đã xoá image: $IMAGE_TAG ($IMAGE_ID)" | tee -a "$LOG_FILE"
    else
      echo "⚠️  BỎ QUA image: $IMAGE_TAG ($IMAGE_ID) — container giữ: $CONTAINERS" | tee -a "$LOG_FILE"
    fi
done

echo "🧾 [$(date)] Hoàn tất dọn image." >> "$LOG_FILE"
