#!/usr/bin/env bash
# Bring a deploy branch up to date with main, keeping it stripped to one project.
#
#   .github/scripts/sync-deploy-branch.sh alogweb
#
# A plain `git merge main` would resurrect every path this branch deliberately
# drops, so the merge is always followed by re-applying the strip list. History
# stays linear and the push is a fast-forward - no force push.
set -euo pipefail

PROJECT="${1:-alogweb}"
BRANCH="deploy/${PROJECT}"
SOURCE="${SYNC_SOURCE_BRANCH:-main}"

# Everything outside this list is removed from the deploy branch. Paths are
# identical to main; only the unrelated projects go.
KEEP_INFO="projects/${PROJECT}/, shared/, .github/workflows/deploy-${PROJECT}.yml, .gitignore"

log()  { printf '\n==> %s\n' "$*"; }
die()  { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

cd "$(git rev-parse --show-toplevel)"

[ -z "$(git status --porcelain)" ] || die "working tree is dirty; commit or stash first"
git show-ref --verify --quiet "refs/heads/$BRANCH" || die "branch $BRANCH does not exist"
git show-ref --verify --quiet "refs/heads/$SOURCE" || die "branch $SOURCE does not exist"

START_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
restore() { git switch -q "$START_BRANCH" 2>/dev/null || true; }
trap restore EXIT

log "Syncing $BRANCH from $SOURCE"
git switch -q "$BRANCH"

if ! git merge -q --no-edit "$SOURCE"; then
    git merge --abort 2>/dev/null || true
    die "merge conflict; resolve it by hand on $BRANCH"
fi

# Anything tracked that is not in the keep list goes.
log "Re-applying the strip list"
mapfile -t DROP < <(
    git ls-files \
      | grep -vE "^(projects/${PROJECT}/|shared/|\.github/workflows/deploy-${PROJECT}\.yml$|\.gitignore$)" \
      || true
)

if [ "${#DROP[@]}" -gt 0 ]; then
    printf '    dropping %s file(s)\n' "${#DROP[@]}"
    git rm -rq --ignore-unmatch -- "${DROP[@]}"
fi

if git diff --cached --quiet && git diff --quiet; then
    log "Already in sync - nothing to commit"
else
    git commit -q -m "chore: sync $BRANCH from $SOURCE

Keeps: ${KEEP_INFO}"
    log "Committed"
fi

log "$BRANCH now has $(git ls-files | wc -l) files"
printf '\nPush when ready:\n    git push origin %s\n' "$BRANCH"
