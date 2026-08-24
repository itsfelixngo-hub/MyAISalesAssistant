# Shared helpers for the alogweb scripts. Sourced, not executed.

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
ENV_FILE="${ALOGWEB_ENV_FILE:-$PROJECT_DIR/.env}"

log()  { printf '\n==> %s\n' "$*"; }
warn() { printf '    ! %s\n' "$*" >&2; }
die()  { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

[ -f "$COMPOSE_FILE" ] || die "compose file not found: $COMPOSE_FILE"
[ -f "$ENV_FILE" ] || die "env file not found: $ENV_FILE (copy .env.production.example and fill it in)"

# Export the project settings so this script can read MYSQL_DATABASE etc.
set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

dc() { docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"; }

# wait_healthy <service> <attempts>
wait_healthy() {
    local service="$1" attempts="${2:-60}" cid status i
    for i in $(seq 1 "$attempts"); do
        cid="$(dc ps -q "$service" 2>/dev/null || true)"
        if [ -n "$cid" ]; then
            status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || echo missing)"
            case "$status" in
                healthy|running) [ "$status" = healthy ] && return 0 ;;
                exited|dead) dc logs --tail=80 "$service"; die "$service exited while starting" ;;
            esac
        fi
        sleep 2
    done
    dc logs --tail=80 "$service"
    die "$service did not become healthy in time"
}

# mysql_query <sql> -> prints a raw, untabbed result
mysql_query() {
    dc exec -T alogweb-mysql sh -c \
        'exec mysql -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" -N -B -e "$0"' "$1" | tr -d '\r'
}

# table_prefix -> the prefix configured for the WordPress container
table_prefix() { printf '%s' "${WORDPRESS_TABLE_PREFIX:-wp_}"; }

# database_seeded -> 0 when the configured "<prefix>options" table exists
database_seeded() {
    local db="${MYSQL_DATABASE:-alogweb_wordpress}" count
    count="$(mysql_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}' AND table_name='$(table_prefix)options'" || echo 0)"
    [ "${count:-0}" -gt 0 ]
}

# database_has_tables -> 0 when the database holds any table at all, whatever
# the prefix. Backups must use this, not database_seeded: switching
# WORDPRESS_TABLE_PREFIX would otherwise look like an empty database and skip
# the safety backup right before a --force reseed drops it.
database_has_tables() {
    local db="${MYSQL_DATABASE:-alogweb_wordpress}" count
    count="$(mysql_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}'" || echo 0)"
    [ "${count:-0}" -gt 0 ]
}

# detected_prefix -> the prefix actually present in the database, or empty.
# Guards against importing a dump whose prefix does not match wp-config, which
# would otherwise show up as a blank "install WordPress" screen.
detected_prefix() {
    local db="${MYSQL_DATABASE:-alogweb_wordpress}"
    mysql_query "SELECT SUBSTRING(table_name, 1, CHAR_LENGTH(table_name) - 7) FROM information_schema.tables WHERE table_schema='${db}' AND table_name LIKE '%options' ORDER BY CHAR_LENGTH(table_name) LIMIT 1"
}

# assert_prefix_matches -> fail loudly when the data uses a different prefix.
# Checks the configured prefix first: a database can legitimately hold several
# "<something>options" tables, so "the shortest one wins" is not a safe answer.
assert_prefix_matches() {
    local found want
    want="$(table_prefix)"
    database_seeded && return 0
    found="$(detected_prefix || true)"
    [ -z "$found" ] && return 0
    die "table prefix mismatch: the database uses '${found}' but WORDPRESS_TABLE_PREFIX is '${want}'.
     Set WORDPRESS_TABLE_PREFIX=${found} in ${ENV_FILE} and redeploy."
}
