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

# nginx holds its configuration in memory and the conf files are bind mounts,
# so `up -d` leaves the previous rules running whenever compose sees no reason
# to recreate this container - which is every deploy that only edits nginx conf.
# The result is a deploy that reports success while the web layer is unchanged.
#
# Test before reloading. nginx refuses a reload it cannot parse and keeps
# serving the old config, so a bad commit degrades to "not applied" rather than
# taking the site down the way a restart would.
log "Reloading nginx configuration"
# Captured, not piped: a pipeline reports the exit status of its last command,
# so `nginx -t | sed` would always look successful no matter what nginx said.
if ! nginx_test="$(dc exec -T alogweb-nginx nginx -t 2>&1)"; then
    printf '%s\n' "$nginx_test" | sed 's/^/    /' >&2
    die "nginx rejected the new configuration - the running config was left in place"
fi
printf '%s\n' "$nginx_test" | sed 's/^/    /'
dc exec -T alogweb-nginx nginx -s reload

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
