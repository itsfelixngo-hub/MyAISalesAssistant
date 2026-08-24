#!/usr/bin/env bash
# Drop the nginx caches.
#
#   ./scripts/purge-cache.sh          # page cache only
#   ./scripts/purge-cache.sh --all    # page cache + proxied screenshot images
#
# Run this after the AI content job, or after editing posts when you do not want
# to wait out the one-hour page cache. Editors logged into wp-admin always
# bypass the cache, so this is only about what anonymous visitors see.
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

purge() {
    local dir="$1" label="$2" before
    before="$(dc exec -T alogweb-nginx sh -c "find '$dir' -type f 2>/dev/null | wc -l" | tr -d '\r')"
    dc exec -T alogweb-nginx sh -c "find '$dir' -mindepth 1 -delete 2>/dev/null || true"
    log "$label: xoá ${before:-0} tệp"
}

if ! dc ps --status running --services 2>/dev/null | grep -q '^alogweb-nginx$'; then
    die "alogweb-nginx is not running"
fi

purge /var/cache/nginx/fastcgi "Page cache"
if [ "${1:-}" = "--all" ]; then
    purge /var/cache/nginx/static "Image cache"
fi

# Warm the front page so the first real visitor does not pay for the miss.
curl -s -o /dev/null "http://127.0.0.1:${HTTP_PORT:-8093}/" || true
log "Done"
