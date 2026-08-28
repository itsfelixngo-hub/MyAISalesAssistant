#!/usr/bin/env bash
# Deploy the alogweb stack (nginx + wordpress-fpm + mysql) on this host.
# Safe to run by hand on the VPS; the GitHub Actions workflow just calls it.
#
#   ./scripts/deploy.sh
#   ALOGWEB_RESEED=1 ./scripts/deploy.sh    # drop + re-import the dump first
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

SCRIPTS_DIR="$PROJECT_DIR/scripts"
RESEED="${ALOGWEB_RESEED:-0}"
HTTP_PORT="${HTTP_PORT:-8093}"

log "Validating compose configuration"
dc config -q

log "Pulling images"
dc pull --quiet || warn "pull failed, continuing with local images"

log "Starting database"
dc up -d alogweb-mysql
wait_healthy alogweb-mysql 90

log "Backing up the current database"
"$SCRIPTS_DIR/backup-db.sh"

log "Seeding database if needed"
if [ "$RESEED" = "1" ]; then
    "$SCRIPTS_DIR/seed-db.sh" --force
else
    "$SCRIPTS_DIR/seed-db.sh"
fi

log "Starting the full stack"
# No --remove-orphans here: it deletes every container sharing this compose
# project name, including ones this stack does not own.
dc up -d
wait_healthy alogweb-wordpress 90
wait_healthy alogweb-nginx 60

# The nginx conf files are bind mounted one file at a time, and a single-file
# bind mount is resolved to an inode when the container starts. rsync does not
# rewrite a file in place - it writes a temporary copy and renames it over the
# original - so every deploy hands the host a brand new inode while the running
# container keeps reading the one it opened at boot. `nginx -s reload` then
# re-reads that same stale copy and reports success, and `nginx -t` validates
# it too, so nothing anywhere looks wrong. Directory mounts like ./theme/apk do
# not have this problem, which is why theme changes have always landed while
# every nginx change since the first deploy silently did not.
#
# Recreating the container is the only thing that re-resolves the mount.
log "Applying nginx configuration"
host_conf="$(md5sum "$PROJECT_DIR/nginx/default.conf" | awk '{print $1}')"
live_conf="$(dc exec -T alogweb-nginx md5sum /etc/nginx/conf.d/default.conf 2>/dev/null | awk '{print $1}')"

if [ "$host_conf" = "$live_conf" ]; then
    log "nginx configuration is already current"
else
    # Validate the new configuration in a throwaway container that mounts it
    # fresh - the running one cannot see it, so `nginx -t` there proves nothing.
    # --no-deps keeps this from starting the rest of the stack.
    if ! conf_test="$(dc run --rm --no-deps -T alogweb-nginx nginx -t 2>&1)"; then
        printf '%s\n' "$conf_test" | sed 's/^/    /' >&2
        die "nginx rejected the new configuration - the running container was left alone"
    fi
    printf '%s\n' "$conf_test" | sed 's/^/    /'
    dc up -d --force-recreate alogweb-nginx
    wait_healthy alogweb-nginx 60
fi

# A dump taken from an older WordPress leaves db_version behind the core
# version, and WordPress then redirects every admin to upgrade.php until the
# schema is migrated. Do it here so a fresh VPS deploy lands on a usable site.
log "Checking database schema version"
if [ "$("$SCRIPTS_DIR/wp.sh" eval 'echo get_option("db_version") == $GLOBALS["wp_db_version"] ? "ok" : "stale";' 2>/dev/null | tr -d '\r')" = stale ]; then
    log "Schema is behind core - running core update-db"
    "$SCRIPTS_DIR/wp.sh" core update-db
else
    log "Schema is current"
fi

log "Container status"
dc ps

# The sort filters read meta derived from _info. A site that has never run
# this - every existing install, the first time this theme is deployed - would
# serve an empty page for "Top rated" and "Lightest".
log "Rebuilding the sort index"
"$SCRIPTS_DIR/wp.sh" alogweb reindex || warn "reindex failed; sorting may be incomplete"

log "Purging the page cache"
"$SCRIPTS_DIR/purge-cache.sh" >/dev/null || warn "cache purge failed, continuing"

log "Smoke testing http://127.0.0.1:${HTTP_PORT}/"
ok=0
for i in $(seq 1 24); do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1:${HTTP_PORT}/" || echo 000)"
    case "$code" in
        200|301|302) log "Site responded with HTTP $code"; ok=1; break ;;
    esac
    sleep 5
done
if [ "$ok" -ne 1 ]; then
    dc logs --tail=60 alogweb-nginx alogweb-wordpress
    die "site did not respond on port ${HTTP_PORT} (last status: ${code:-none})"
fi

log "Deploy finished: ${SITE_URL:-http://127.0.0.1:$HTTP_PORT}"
