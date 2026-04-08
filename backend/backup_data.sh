# === TÊN CONTAINER ===
NEST_CONTAINER="nestjs_pro_samsung_edu"
MONGO_CONTAINER="mongo_pro_samsung_edu"

# Khai báo biến backup database
DATABASE="dbsamsung_edu"
TIMESTAMP=$(date +%F_%H-%M-%S)
OUT_DIR="/data/backup/${DATABASE}-${TIMESTAMP}"

HOST_BACKUP_ROOT="./data/backup"
if [ ! -d "$HOST_BACKUP_ROOT" ]; then
echo "📂 Tạo lại thư mục backup trên host: $HOST_BACKUP_ROOT"
mkdir -p "$HOST_BACKUP_ROOT"
fi
# Dump DB vào thư mục OUT_DIR
echo "📦 Dump MongoDB vào $OUT_DIR..."
docker exec "$MONGO_CONTAINER" sh -c "mongodump --db=$DATABASE --out=$OUT_DIR"
HOST_OUT_DIR="./data/backup/${DATABASE}-${TIMESTAMP}"
# Đợi 1 chút nếu dump chưa sync ra host
sleep 1