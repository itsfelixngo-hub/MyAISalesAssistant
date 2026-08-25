#!/usr/bin/env bash
# Import the alogweb SQL dump into the running MySQL container.
#
#   ./scripts/seed-db.sh            # import only when the database is empty
#   ./scripts/seed-db.sh --force    # DROP the database and re-import (destructive)
#
# The dump is gitignored, so place it on the host yourself:
#   projects/alogweb/database/<name>.sql        (.sql or .sql.gz)
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

FORCE=0
[ "${1:-}" = "--force" ] && FORCE=1

DB_NAME="${MYSQL_DATABASE:-alogweb_wordpress}"
DB_USER="${MYSQL_USER:-alogweb}"

# Find the dump. No name is privileged: preferring a familiar filename means a
# stale dump left in the directory silently wins over the one just copied there,
# and the import looks like it worked. Exactly one, or say so.
DUMP="${ALOGWEB_DUMP:-}"
if [ -z "$DUMP" ]; then
    mapfile -t FOUND < <(find "$PROJECT_DIR/database" -maxdepth 1 -type f \
        \( -name '*.sql' -o -name '*.sql.gz' \) | sort)
    case "${#FOUND[@]}" in
        0) : ;;
        1) DUMP="${FOUND[0]}" ;;
        *) die "several dumps in $PROJECT_DIR/database - name the one you want:
     ALOGWEB_DUMP=$PROJECT_DIR/database/<file> $0 ${1:-}
     found: $(printf '%s ' "${FOUND[@]##*/}")" ;;
    esac
fi

wait_healthy alogweb-mysql 90

if [ "$FORCE" -eq 0 ]; then
    assert_prefix_matches
fi

if [ "$FORCE" -eq 1 ]; then
    # Always drop, never conditionally: after a prefix change the configured
    # tables are absent, and skipping the drop would import the dump on top of
    # the old data and leave two prefixes in one database.
    log "--force given: dropping database '$DB_NAME'"
    mysql_query "DROP DATABASE IF EXISTS \`${DB_NAME}\`" >/dev/null
    mysql_query "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci" >/dev/null
    mysql_query "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'" >/dev/null
elif database_seeded; then
    log "Database '$DB_NAME' already contains WordPress tables - skipping import"
    exit 0
fi

[ -n "$DUMP" ] && [ -f "$DUMP" ] || die "no SQL dump found. Copy one into $PROJECT_DIR/database/ or set ALOGWEB_DUMP"

log "Importing $(basename "$DUMP") ($(du -h "$DUMP" | cut -f1)) into '$DB_NAME'"
import() {
    dc exec -T alogweb-mysql sh -c \
        'exec mysql -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 "$MYSQL_DATABASE"'
}
case "$DUMP" in
    *.gz) gunzip -c "$DUMP" | import ;;
    *)    import < "$DUMP" ;;
esac

assert_prefix_matches
database_seeded || die "import finished but no $(table_prefix)options table was created - is the dump empty?"

posts="$(mysql_query "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`$(table_prefix)posts\`" || echo '?')"
log "Import complete - ${posts} rows in $(table_prefix)posts"
