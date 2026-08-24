#!/usr/bin/env bash
# Rebuild a deploy branch as "the source branch's tree, filtered to one project".
#
#   .github/scripts/sync-deploy-branch.sh alogweb
#
# The deploy branch keeps main's exact layout and drops every path that belongs
# to another project, so the runner checks out only what it deploys.
#
# Why this does not merge: the deploy branch deletes paths that main keeps, so
# any later edit to one of those paths on main produces a modify/delete conflict
# on every single sync. Instead the tree is derived from scratch each time, with
# both branches recorded as parents. That cannot conflict, and it never touches
# the working tree - which matters here, because the theme directory is bind
# mounted into running containers.
set -euo pipefail

PROJECT="${1:-alogweb}"
BRANCH="deploy/${PROJECT}"
SOURCE="${SYNC_SOURCE_BRANCH:-main}"

# Paths kept on the deploy branch. Everything else is dropped.
KEEP_RE="^(projects/${PROJECT}/|shared/|\.github/workflows/deploy-${PROJECT}\.yml$|\.gitignore$)"

log() { printf '\n==> %s\n' "$*"; }
die() { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

cd "$(git rev-parse --show-toplevel)"
git show-ref --verify --quiet "refs/heads/$SOURCE" || die "branch $SOURCE does not exist"

log "Building $BRANCH from $SOURCE"

TMP_INDEX="$(mktemp "${TMPDIR:-/tmp}/sync-index.XXXXXX")"
cleanup() { rm -f "$TMP_INDEX"; }
trap cleanup EXIT

# Everything below runs against a scratch index; the real index and the working
# tree are untouched.
export GIT_INDEX_FILE="$TMP_INDEX"
git read-tree "$SOURCE"

mapfile -t DROP < <(git ls-files | grep -vE "$KEEP_RE" || true)
if [ "${#DROP[@]}" -gt 0 ]; then
    git rm -rq --cached --ignore-unmatch -- "${DROP[@]}"
fi

KEPT="$(git ls-files | wc -l)"
[ "$KEPT" -gt 0 ] || die "the keep list matched nothing - check PROJECT=$PROJECT"

NEW_TREE="$(git write-tree)"
unset GIT_INDEX_FILE

if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    OLD_TREE="$(git rev-parse "${BRANCH}^{tree}")"
    if [ "$NEW_TREE" = "$OLD_TREE" ]; then
        log "Already in sync - $BRANCH already matches $SOURCE (${KEPT} files)"
        exit 0
    fi
    PARENTS=(-p "$(git rev-parse "$BRANCH")" -p "$(git rev-parse "$SOURCE")")
    ACTION="Updated"
else
    PARENTS=(-p "$(git rev-parse "$SOURCE")")
    ACTION="Created"
fi

COMMIT="$(git commit-tree "$NEW_TREE" "${PARENTS[@]}" -m "chore: sync ${BRANCH} from ${SOURCE}

Tree derived from ${SOURCE}, filtered to ${PROJECT}. Dropped ${#DROP[@]} path(s)
that belong to other projects; layout is otherwise identical.")"

git update-ref "refs/heads/$BRANCH" "$COMMIT"

log "$ACTION $BRANCH: ${KEPT} files kept, ${#DROP[@]} dropped"
printf '\nPush when ready:\n    git push origin %s\n' "$BRANCH"
