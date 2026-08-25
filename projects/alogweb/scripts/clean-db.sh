#!/usr/bin/env bash
# Remove what should not travel to a new server, then report what went.
#
#   ./scripts/clean-db.sh              # show what would be removed
#   ./scripts/clean-db.sh --yes        # remove it
#   ./scripts/clean-db.sh --yes --comments   # also delete spam and pending comments
#
# Takes a backup first. Published posts, drafts, users and the AI backup history
# are never touched.
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

RUN=0; COMMENTS=0
for arg in "$@"; do
    case "$arg" in
        --yes) RUN=1 ;;
        --comments) COMMENTS=1 ;;
        *) die "unknown option: $arg" ;;
    esac
done

wait_healthy alogweb-mysql 90
database_seeded || die "database is empty"

DB="$MYSQL_DATABASE"
P="$(table_prefix)"
q() { mysql_query "$1"; }
count() { q "SELECT COUNT(*) $1" | tail -1; }

REV=$(count "FROM \`$DB\`.\`${P}posts\` WHERE post_type='revision'")
AUTO=$(count "FROM \`$DB\`.\`${P}posts\` WHERE post_status='auto-draft' OR (post_status='trash' AND post_title='Auto Draft')")
LOCK=$(count "FROM \`$DB\`.\`${P}postmeta\` WHERE meta_key IN ('_edit_lock','_edit_last')")
OEMB=$(count "FROM \`$DB\`.\`${P}postmeta\` WHERE meta_key LIKE '\\_oembed\\_%'")
TRAN=$(count "FROM \`$DB\`.\`${P}options\` WHERE option_name LIKE '%\\_transient\\_%'")
CSPAM=$(count "FROM \`$DB\`.\`${P}comments\` WHERE comment_approved IN ('spam','trash')")
CPEND=$(count "FROM \`$DB\`.\`${P}comments\` WHERE comment_approved='0'")
COK=$(count "FROM \`$DB\`.\`${P}comments\` WHERE comment_approved='1'")

printf '\n  %-34s %s\n' "post revisions" "${REV:-0}"
printf '  %-34s %s\n' "auto-drafts and trashed drafts" "${AUTO:-0}"
printf '  %-34s %s\n' "edit locks" "${LOCK:-0}"
printf '  %-34s %s\n' "oEmbed caches" "${OEMB:-0}"
printf '  %-34s %s\n' "transients" "${TRAN:-0}"
printf '  %-34s %s\n' "AI job state" "reset"
if [ "$COMMENTS" -eq 1 ]; then
    printf '  %-34s %s\n' "comments: spam/trash" "${CSPAM:-0}"
    printf '  %-34s %s\n' "comments: pending" "${CPEND:-0}"
    printf '  %-34s %s (kept)\n' "comments: approved" "${COK:-0}"
else
    printf '  %-34s %s pending, %s approved (kept - pass --comments)\n' "comments" "${CPEND:-0}" "${COK:-0}"
fi
echo

if [ "$RUN" -ne 1 ]; then
    log "Dry run. Add --yes to apply."
    exit 0
fi

log "Backing up first"
"$(dirname "${BASH_SOURCE[0]}")/backup-db.sh" >/dev/null

log "Cleaning"
# Revisions and their meta. Published content, drafts and _aipcw_backup_history
# are untouched: the backup history is the only copy of the pre-AI text.
q "DELETE FROM \`$DB\`.\`${P}posts\` WHERE post_type='revision'" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}posts\` WHERE post_status='auto-draft' OR (post_status='trash' AND post_title='Auto Draft')" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}postmeta\` WHERE meta_key IN ('_edit_lock','_edit_last')" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}postmeta\` WHERE meta_key LIKE '\\_oembed\\_%'" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}options\` WHERE option_name LIKE '%\\_transient\\_%'" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}options\` WHERE option_name = 'aipcw_background_job'" >/dev/null

if [ "$COMMENTS" -eq 1 ]; then
    q "DELETE FROM \`$DB\`.\`${P}comments\` WHERE comment_approved IN ('spam','trash','0')" >/dev/null
fi

# Meta whose row is gone, including any left by the deletions above.
# Single-table DELETE on purpose: the multi-table LEFT JOIN form needs a default
# database, and these run over a connection that has not selected one.
q "DELETE FROM \`$DB\`.\`${P}postmeta\`
   WHERE post_id NOT IN (SELECT ID FROM \`$DB\`.\`${P}posts\`)" >/dev/null
q "DELETE FROM \`$DB\`.\`${P}commentmeta\`
   WHERE comment_id NOT IN (SELECT comment_ID FROM \`$DB\`.\`${P}comments\`)" >/dev/null

log "Recounting comments on every post"
q "UPDATE \`$DB\`.\`${P}posts\` p SET comment_count =
     (SELECT COUNT(*) FROM \`$DB\`.\`${P}comments\` c
       WHERE c.comment_post_ID = p.ID AND c.comment_approved = '1')" >/dev/null

log "Optimising tables"
dc exec -T alogweb-mysql sh -c \
  'exec mysqlcheck --optimize --default-character-set=utf8mb4 -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  >/dev/null 2>&1 || warn "optimise skipped"

log "Done"
