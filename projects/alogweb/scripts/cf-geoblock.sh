#!/usr/bin/env bash
# Block visitors from a country at Cloudflare's edge, without locking out wp-admin.
#
#   ./scripts/cf-geoblock.sh                 # show the current rule
#   ./scripts/cf-geoblock.sh on              # block Vietnam
#   ./scripts/cf-geoblock.sh on --countries VN,CN
#   ./scripts/cf-geoblock.sh on --action managed_challenge
#   ./scripts/cf-geoblock.sh on --allow-ip 1.2.3.4 --allow-ip 5.6.0.0/16
#   ./scripts/cf-geoblock.sh off             # keep the rule, stop enforcing it
#   ./scripts/cf-geoblock.sh remove          # delete the rule
#   ./scripts/cf-geoblock.sh on --dry-run    # print the expression, call nothing
#
# This writes one WAF custom rule into the zone's http_request_firewall_custom
# ruleset and finds it again by its description, so running "on" twice edits the
# same rule instead of stacking duplicates. Rules created in the dashboard are
# left alone.
#
# The rule never covers the paths in ALLOW_PATHS below. wp-admin from inside the
# blocked country has to keep working, and the admin screens are not self
# contained: the block editor talks to /wp-json, and both the login page and
# wp-admin pull CSS and JS out of /wp-includes and /wp-content. Block those and
# the admin loads as unstyled HTML that cannot save a post. The cost is that a
# visitor in the blocked country can still fetch an image or a stylesheet by its
# direct URL - pages, feeds and search are what this closes.
#
# CLOUDFLARE_API_TOKEN needs the Zone / WAF / Edit permission on this zone.
# The cache-purge token does not have it; Cloudflare answers a token that is
# missing the permission with "Actor does not have permission", not with a
# vague failure, so the message on screen says which one is wrong.
set -euo pipefail

# shellcheck source=_common.sh
. "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

API="https://api.cloudflare.com/client/v4"
PHASE="http_request_firewall_custom"

# The rule is found again by this string. Changing it orphans the rule that is
# already live in the zone, so it stays constant while countries and action move.
RULE_DESCRIPTION="alogweb geo-block"

# Prefix matches, so "/wp-login.php" also covers "/wp-login.php" with a query
# string and "/wp-admin" covers the redirect to "/wp-admin/".
ALLOW_PATHS=(/wp-admin /wp-login.php /wp-json /wp-includes/ /wp-content/ /wp-cron.php)

COMMAND=status
COUNTRIES="${ALOGWEB_GEOBLOCK_COUNTRIES:-VN}"
ACTION="${ALOGWEB_GEOBLOCK_ACTION:-block}"
ALLOW_IPS=()
DRY_RUN=0

[ $# -gt 0 ] && case "$1" in
    status|on|off|remove) COMMAND="$1"; shift ;;
esac
while [ $# -gt 0 ]; do
    case "$1" in
        --countries) COUNTRIES="${2:?--countries needs a value}"; shift 2 ;;
        --action)    ACTION="${2:?--action needs a value}"; shift 2 ;;
        --allow-ip)  ALLOW_IPS+=("${2:?--allow-ip needs a value}"); shift 2 ;;
        --dry-run)   DRY_RUN=1; shift ;;
        *) die "unknown option: $1" ;;
    esac
done

case "$ACTION" in
    block|managed_challenge|js_challenge) ;;
    *) die "unknown action: $ACTION (block, managed_challenge or js_challenge)" ;;
esac

[ -n "${CLOUDFLARE_API_TOKEN:-}" ] || die "CLOUDFLARE_API_TOKEN is empty in $ENV_FILE"
[ -n "${CLOUDFLARE_ZONE_ID:-}" ]   || die "CLOUDFLARE_ZONE_ID is empty in $ENV_FILE"
command -v python3 >/dev/null || die "python3 is required to read Cloudflare's replies"

# ---------------------------------------------------------------- JSON helpers
# Small readers over the API replies. The JSON goes in as an argument, not on
# stdin: these scripts arrive on stdin themselves, so a pipe into them would be
# swallowed by the heredoc and read as an empty document.

# json_field <json> <dotted.path> - a value, empty when the path is absent.
json_field() {
    python3 - "$1" "$2" <<'PY'
import json, sys
node = json.loads(sys.argv[1])
for key in sys.argv[2].split('.'):
    node = node.get(key) if isinstance(node, dict) else None
    if node is None:
        break
print('' if node is None else node if isinstance(node, str) else json.dumps(node))
PY
}

# json_errors <json> - Cloudflare's own error text, empty when it succeeded.
json_errors() {
    python3 - "$1" <<'PY'
import json, sys
raw = sys.argv[1].strip()
if not raw:
    print('Cloudflare sent an empty reply'); sys.exit(0)
try:
    body = json.loads(raw)
except ValueError:
    print('Cloudflare sent a reply this could not read: ' + raw[:200]); sys.exit(0)
if body.get('success'):
    sys.exit(0)
print(' / '.join(
    ': '.join(p for p in (str(e.get('code', '')), e.get('message', '')) if p)
    for e in body.get('errors') or []) or 'Cloudflare rejected the request.')
PY
}

# rule_field <ruleset json> <field> - that field of the rule named
# RULE_DESCRIPTION. Empty when the zone has no such rule yet.
rule_field() {
    python3 - "$1" "$RULE_DESCRIPTION" "$2" <<'PY'
import json, sys
body, want, field = json.loads(sys.argv[1]), sys.argv[2], sys.argv[3]
for rule in ((body.get('result') or {}).get('rules') or []):
    if rule.get('description') == want:
        value = rule.get(field)
        print('' if value is None else value if isinstance(value, str) else json.dumps(value))
        break
PY
}

# rule_body <enabled> - the rule as Cloudflare wants it, with the expression
# JSON-encoded rather than pasted into a string by hand.
rule_body() {
    python3 - "$EXPRESSION" "$RULE_DESCRIPTION" "$ACTION" "$1" <<'PY'
import json, sys
expression, description, action, enabled = sys.argv[1:5]
print(json.dumps({
    'description': description,
    'expression': expression,
    'action': action,
    'enabled': enabled == 'true',
}))
PY
}

# ----------------------------------------------------------------- the request
# One temp file for the reply body, so the HTTP status can be read from curl
# itself instead of being guessed from what came back.
BODY_FILE="$(mktemp "${TMPDIR:-/tmp}/alogweb-cf.XXXXXX")"
trap 'rm -f "$BODY_FILE"' EXIT

# cf_call <METHOD> <PATH> [BODY] - sets CF_BODY to the reply and CF_ERROR to
# Cloudflare's own message, empty when the call worked. Deliberately not a
# subshell: the caller reads both variables.
CF_BODY=""; CF_ERROR=""
cf_call() {
    local method="$1" path="$2" body="${3-}" code
    local args=(-sS -o "$BODY_FILE" -w '%{http_code}' -X "$method"
        -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN"
        -H "Content-Type: application/json")
    [ -n "$body" ] && args+=(--data "$body")

    CF_BODY=""; CF_ERROR=""
    if ! code="$(curl "${args[@]}" "$API/$path")"; then
        CF_ERROR="could not reach $API"
        return 0
    fi
    CF_BODY="$(cat "$BODY_FILE")"

    # An empty body only counts as an answer when the status says so: a proxy
    # or blocked egress returns nothing too, and that must not read as success.
    if [ -z "${CF_BODY//[[:space:]]/}" ]; then
        case "$code" in
            2*) CF_ERROR="" ;;
            *)  CF_ERROR="Cloudflare answered HTTP $code with an empty body" ;;
        esac
        return 0
    fi
    CF_ERROR="$(json_errors "$CF_BODY")"
}

# cf <METHOD> <PATH> [BODY] - the same call, dying on whatever Cloudflare said.
cf() {
    cf_call "$@"
    [ -z "$CF_ERROR" ] || die "$CF_ERROR"
}

# ------------------------------------------------------------- the expression
build_expression() {
    local countries=() paths=() country path ip clause

    IFS=', ' read -r -a countries <<< "$COUNTRIES"
    local country_list=""
    for country in "${countries[@]}"; do
        [ -n "$country" ] || continue
        country_list+="\"${country^^}\" "
    done
    [ -n "$country_list" ] || die "--countries is empty"
    clause="ip.src.country in {${country_list% }}"

    for path in "${ALLOW_PATHS[@]}"; do
        paths+=("starts_with(http.request.uri.path, \"$path\")")
    done
    local allow_paths="${paths[0]}"
    for path in "${paths[@]:1}"; do allow_paths+=" or $path"; done
    clause+=" and not ($allow_paths)"

    if [ ${#ALLOW_IPS[@]} -gt 0 ]; then
        local ip_list=""
        for ip in "${ALLOW_IPS[@]}"; do ip_list+="$ip "; done
        clause+=" and not (ip.src in {${ip_list% }})"
    fi

    printf '(%s)' "$clause"
}
EXPRESSION="$(build_expression)"

# ----------------------------------------------------------------- the ruleset
# The zone may have no custom-rules entrypoint yet: an untouched zone only grows
# one when the first rule is written. GET says 404 in that case, so "on" creates
# it and the read-only commands report an empty zone rather than an error.
ENTRYPOINT="zones/$CLOUDFLARE_ZONE_ID/rulesets/phases/$PHASE/entrypoint"

log "Zone $CLOUDFLARE_ZONE_ID, rule \"$RULE_DESCRIPTION\""
printf '    %s %s\n' "$ACTION" "$EXPRESSION"

if [ "$DRY_RUN" = 1 ]; then
    log "Dry run - nothing was sent to Cloudflare"
    exit 0
fi

cf_call GET "$ENTRYPOINT"
ruleset="$CF_BODY"
ruleset_errors="$CF_ERROR"
if [ -n "$ruleset_errors" ]; then
    ruleset=""
    ruleset_id=""
    rule_id=""
else
    ruleset_id="$(json_field "$ruleset" result.id)"
    rule_id="$(rule_field "$ruleset" id)"
fi

case "$COMMAND" in
    status)
        if [ -z "$ruleset_id" ]; then
            warn "$ruleset_errors"
            log "Could not read this zone's custom rules. A zone that has never had one answers the same way, and then nothing is blocked."
            exit 0
        fi
        if [ -z "$rule_id" ]; then
            log "Rule not found - this zone is not geo-blocking anything through this script"
            exit 0
        fi
        enabled="$(rule_field "$ruleset" enabled)"
        log "Rule $rule_id is $([ "$enabled" = true ] && echo ENABLED || echo disabled)"
        printf '    action:     %s\n' "$(rule_field "$ruleset" action)"
        printf '    expression: %s\n' "$(rule_field "$ruleset" expression)"
        printf '\n    The line above is what is live. The one at the top is what "on" would write.\n'
        ;;

    on)
        if [ -z "$ruleset_id" ]; then
            log "Could not read a custom-rules ruleset - creating one with this rule"
            cf PUT "$ENTRYPOINT" "$(python3 - "$(rule_body true)" <<'PY'
import json, sys
print(json.dumps({'rules': [json.loads(sys.argv[1])]}))
PY
)"
            log "Created. $ACTION is live for $COUNTRIES, wp-admin excluded."
            exit 0
        fi
        if [ -n "$rule_id" ]; then
            cf PATCH "zones/$CLOUDFLARE_ZONE_ID/rulesets/$ruleset_id/rules/$rule_id" "$(rule_body true)"
            log "Updated rule $rule_id. $ACTION is live for $COUNTRIES, wp-admin excluded."
        else
            cf POST "zones/$CLOUDFLARE_ZONE_ID/rulesets/$ruleset_id/rules" "$(rule_body true)"
            log "Added the rule. $ACTION is live for $COUNTRIES, wp-admin excluded."
        fi
        ;;

    off)
        # Without the ruleset there is no way to tell "nothing to do" from "the
        # token cannot read this zone", so say what Cloudflare said.
        [ -z "$ruleset_errors" ] || die "$ruleset_errors"
        [ -n "$rule_id" ] || { log "Rule not found - nothing to switch off"; exit 0; }
        cf PATCH "zones/$CLOUDFLARE_ZONE_ID/rulesets/$ruleset_id/rules/$rule_id" "$(rule_body false)"
        log "Disabled. The rule stays in the zone so \"on\" can switch it back."
        ;;

    remove)
        [ -z "$ruleset_errors" ] || die "$ruleset_errors"
        [ -n "$rule_id" ] || { log "Rule not found - nothing to remove"; exit 0; }
        cf DELETE "zones/$CLOUDFLARE_ZONE_ID/rulesets/$ruleset_id/rules/$rule_id"
        log "Removed rule $rule_id."
        ;;
esac
