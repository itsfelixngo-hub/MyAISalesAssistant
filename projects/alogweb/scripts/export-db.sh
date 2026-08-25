#!/usr/bin/env bash
# Export this database for import on another server.
#
#   ./scripts/export-db.sh                 # everything, transients dropped
#   ./scripts/export-db.sh --slim          # also drop post revisions
#   ./scripts/export-db.sh -o /tmp/x.sql.gz
#
# The result is a gzipped dump written 0600. Read the warnings it prints before
# copying it anywhere: the options table carries live credentials.
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

SLIM=0
OUT=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        --slim) SLIM=1 ;;
        -o) shift; OUT="${1:-}" ;;
        *) die "unknown option: $1" ;;
    esac
    shift
done

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${OUT:-$PROJECT_DIR/backups/alogweb-export-${STAMP}.sql.gz}"
mkdir -p "$(dirname "$OUT")"

wait_healthy alogweb-mysql 90
database_seeded || die "database is empty - nothing to export"

PREFIX="$(table_prefix)"

# Transients are a cache: captcha challenges, rate-limit stamps, WordPress's own
# scratch space. Carrying them to another server is pure noise, and expired
# captcha tokens would be honoured there for as long as they had left to run.
log "Dropping transients"
before="$(mysql_query "SELECT COUNT(*) FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}options\` WHERE option_name LIKE '%\\_transient\\_%'")"
mysql_query "DELETE FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}options\` WHERE option_name LIKE '%\\_transient\\_%'" >/dev/null
log "Removed ${before:-0} transient rows"

if [ "$SLIM" -eq 1 ]; then
    # Revisions hold the pre-AI text of every rewritten post, so this is only
    # safe once you are sure the new content is the one you want to keep.
    revs="$(mysql_query "SELECT COUNT(*) FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}posts\` WHERE post_type='revision'")"
    log "--slim: dropping ${revs:-0} revisions"
    mysql_query "DELETE FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}posts\` WHERE post_type='revision'" >/dev/null
    mysql_query "DELETE pm FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}postmeta\` pm
                 LEFT JOIN \`${MYSQL_DATABASE}\`.\`${PREFIX}posts\` p ON p.ID = pm.post_id
                 WHERE p.ID IS NULL" >/dev/null
fi

log "Dumping to $(basename "$OUT")"
dc exec -T alogweb-mysql sh -c \
    'exec mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$OUT.part"
mv "$OUT.part" "$OUT"
chmod 600 "$OUT"

posts="$(mysql_query "SELECT COUNT(*) FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}posts\` WHERE post_type='post' AND post_status='publish'")"
backups="$(mysql_query "SELECT COUNT(*) FROM \`${MYSQL_DATABASE}\`.\`${PREFIX}postmeta\` WHERE meta_key='_aipcw_backup_history'")"

log "Done: $OUT ($(du -h "$OUT" | cut -f1))"
cat <<INFO

  Published posts        ${posts:-?}
  Pre-AI content kept    ${backups:-0} posts still carry _aipcw_backup_history
                         (the only way back if the rewritten copy reads worse)

  This dump contains live credentials:
    - the Gemini API key in ${PREFIX}options.aipcw_settings
    - the administrator password hash in ${PREFIX}users

  It is written 0600 and .gitignore already excludes *.sql.gz here. Copy it with
  scp, not through anything that keeps a copy, and delete it from both machines
  once the import is verified.

  On the new server:
    scp $(basename "$OUT") deploy@HOST:/home/deploy/apps/alogweb/projects/alogweb/database/
    ./scripts/deploy.sh          # seeds only because the database is empty

INFO
