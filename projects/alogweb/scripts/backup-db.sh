#!/usr/bin/env bash
# Dump the live alogweb database to projects/alogweb/backups/ and prune old files.
# No-op (exit 0) when the database has not been seeded yet.
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

BACKUP_DIR="${ALOGWEB_BACKUP_DIR:-$PROJECT_DIR/backups}"
KEEP_DAYS="${ALOGWEB_BACKUP_KEEP_DAYS:-14}"
STAMP="${1:-$(date +%Y%m%d-%H%M%S)}"

wait_healthy alogweb-mysql 90

if ! database_has_tables; then
    log "Database is empty - nothing to back up"
    exit 0
fi

mkdir -p "$BACKUP_DIR"
target="$BACKUP_DIR/alogweb-${STAMP}.sql.gz"

log "Backing up to $target"
dc exec -T alogweb-mysql sh -c \
    'exec mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$target.part"
mv "$target.part" "$target"

log "Backup done ($(du -h "$target" | cut -f1)); pruning backups older than ${KEEP_DAYS} days"
find "$BACKUP_DIR" -type f -name 'alogweb-*.sql.gz' -mtime "+${KEEP_DAYS}" -delete
