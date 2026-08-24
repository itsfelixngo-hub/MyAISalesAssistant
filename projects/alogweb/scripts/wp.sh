#!/usr/bin/env bash
# Run WP-CLI against the alogweb stack using a throwaway container, so the
# running stack stays at three containers.
#
#   ./scripts/wp.sh core version
#   ./scripts/wp.sh rewrite flush
#   ./scripts/wp.sh alogweb check-store-links --limit=50
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

[ "$#" -gt 0 ] || die "usage: wp.sh <wp-cli args...>"

# Without this the failure surfaces as a raw "network ... not found" from the
# docker daemon, which does not say the obvious thing: either the stack is down,
# or this is being run from a checkout whose .env names a different network.
NETWORK="${ALOGWEB_NETWORK:-alogweb_net}"
if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
    die "docker network '$NETWORK' does not exist.
     Either the stack is not running (./scripts/deploy.sh), or you are in a
     different checkout - this one reads $ENV_FILE.
     Networks present: $(docker network ls --format '{{.Name}}' | paste -sd' ' -)"
fi

# Without this the throwaway container skips WORDPRESS_CONFIG_EXTRA and WP-CLI
# falls back to the siteurl stored in the dump, printing the wrong URLs.
WP_CLI_CONFIG_EXTRA="define('WP_HOME', '${SITE_URL:-http://localhost:8093}');
define('WP_SITEURL', '${SITE_URL:-http://localhost:8093}');
define('DISABLE_WP_CRON', ${DISABLE_WP_CRON:-true});
define('FS_METHOD', 'direct');"

exec docker run --rm -i \
    --network "${ALOGWEB_NETWORK:-alogweb_net}" \
    -e WORDPRESS_CONFIG_EXTRA="$WP_CLI_CONFIG_EXTRA" \
    --user 33:33 \
    -e WORDPRESS_DB_HOST=alogweb-mysql:3306 \
    -e WORDPRESS_DB_NAME="${MYSQL_DATABASE:-alogweb_wordpress}" \
    -e WORDPRESS_DB_USER="${MYSQL_USER:-alogweb}" \
    -e WORDPRESS_DB_PASSWORD="${MYSQL_PASSWORD}" \
    -e WORDPRESS_TABLE_PREFIX="${WORDPRESS_TABLE_PREFIX:-apk_}" \
    -e AIPCW_PROJECT_ID=alogweb \
    -v "${ALOGWEB_WORDPRESS_VOLUME:-alogweb_wordpress_data}:/var/www/html" \
    -v "$PROJECT_DIR/../../shared/plugins/ai-post-content-writer:/var/www/html/wp-content/plugins/my-ai-sales-assistant" \
    -v "$PROJECT_DIR/theme/apk:/var/www/html/wp-content/themes/apk" \
    -w /var/www/html \
    wordpress:cli-php8.1 wp --path=/var/www/html "$@"
