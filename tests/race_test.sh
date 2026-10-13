#!/usr/bin/env bash
# Race-condition test for H4G team mode. Fires concurrent requests at a running deployment.
#
#   !! Run it against a STAGING database (e.g. a Vercel preview deployment with its own DB).
#   !! Test 1 uses up the event's single First Blood. Reset afterwards (see MIGRATION.md).
#   !! Do not use `php -S` locally: it serves one request at a time, so nothing races.
#
# Usage:
#   BASE_URL=https://your-preview.vercel.app \
#   A_CAT=forensics A_PROBLEM='hex warmup'     A_FLAG='<flag for A>' \
#   B_CAT=forensics B_PROBLEM='hidden image'   B_FLAG='<flag for B>' \
#   bash tests/race_test.sh
#
# A and B must be two different challenges. Flags are read from the environment only and are
# never printed. Requires bash + curl. To load a flag without pasting it (php -n avoids startup
# warnings leaking into the value):
#   A_FLAG="$(php -n -r '$p = require "api/_problems.php"; echo $p[$argv[1]][$argv[2]]["answer"];' forensics 'hex warmup')"
#
# Tests:
#   1. Two teams solve DIFFERENT challenges at the same instant -> exactly one first_blood:true.
#      (Needs First Blood unclaimed at start: DELETE FROM event_first_blood;)
#   2. Several teams solve the SAME challenge at the same instant -> all ranks distinct.
#   3. A team with 3 members; two users join for the last slot at the same instant -> exactly one succeeds.

set -u

: "${BASE_URL:?set BASE_URL}"
: "${A_CAT:?set A_CAT}"; : "${A_PROBLEM:?set A_PROBLEM}"; : "${A_FLAG:?set A_FLAG}"
: "${B_CAT:?set B_CAT}"; : "${B_PROBLEM:?set B_PROBLEM}"; : "${B_FLAG:?set B_FLAG}"
SAME_TEAMS="${SAME_TEAMS:-4}"   # teams racing on the same challenge in test 2

BASE_URL="${BASE_URL%/}"
RUN="$(date +%s | tail -c 6)$((RANDOM % 10))"   # 6 digits, keeps usernames <= 20 chars
WORK="$(mktemp -d)"
PASS='racetest-pass-123'
FAILS=0
trap 'rm -rf "$WORK"' EXIT

ok()   { echo "  [PASS] $*"; }
bad()  { echo "  [FAIL] $*"; FAILS=$((FAILS + 1)); }
die()  { echo "  [ABORT] $*"; exit 2; }

jar() { echo "$WORK/$1.jar"; }

# GET a page and pull the CSRF token out of the hidden field or the meta tag.
csrf_from() {
  curl -s -b "$(jar "$1")" -c "$(jar "$1")" "$BASE_URL/$2" \
    | grep -oE '(name="csrf" value|name="csrf-token" content)="[a-f0-9]{64}"' | head -1 | grep -oE '[a-f0-9]{64}'
}

register() {  # register <user>
  local tok code
  tok="$(csrf_from "$1" 'login.php?mode=register')"
  [ -n "$tok" ] || die "no CSRF token on login.php for $1 (is BASE_URL right?)"
  code="$(curl -s -o /dev/null -w '%{http_code}' -b "$(jar "$1")" -c "$(jar "$1")" \
    --data-urlencode action=register --data-urlencode "username=$1" \
    --data-urlencode "password=$PASS" --data-urlencode "confirm=$PASS" --data-urlencode "csrf=$tok" \
    "$BASE_URL/login.php?mode=register")"
  [ "$code" = 302 ] || die "register $1 failed (HTTP $code)"
}

create_team() {  # create_team <user> <team name>
  local tok code
  tok="$(csrf_from "$1" team-setup.php)"
  code="$(curl -s -o /dev/null -w '%{http_code}' -b "$(jar "$1")" -c "$(jar "$1")" \
    --data-urlencode action=create --data-urlencode "team_name=$2" --data-urlencode "csrf=$tok" \
    "$BASE_URL/team-setup.php")"
  [ "$code" = 303 ] || die "create team '$2' by $1 failed (HTTP $code)"
}

invite_code() {  # invite_code <user>
  curl -s -b "$(jar "$1")" "$BASE_URL/team.php" \
    | grep -oE 'id="invite-code">[A-Z0-9]{4}-[A-Z0-9]{4}' | grep -oE '[A-Z0-9]{4}-[A-Z0-9]{4}'
}

# join_req <user> <code> <tokenfile> -> writes "<http code>" and the body to $WORK/join_<user>.*
join_req() {
  curl -s -o "$WORK/join_$1.body" -w '%{http_code}' -b "$(jar "$1")" -c "$(jar "$1")" \
    --data-urlencode action=join --data-urlencode "invite_code=$2" --data-urlencode "csrf=$(cat "$3")" \
    "$BASE_URL/team-setup.php" > "$WORK/join_$1.code"
}

# submit_req <user> <cat> <problem> <flag> <tokenfile> -> JSON in $WORK/sub_<user>.json
submit_req() {
  curl -s -b "$(jar "$1")" -c "$(jar "$1")" \
    --data-urlencode "cat=$2" --data-urlencode "problem=$3" --data-urlencode "flag=$4" \
    --data-urlencode "csrf=$(cat "$5")" "$BASE_URL/check.php" > "$WORK/sub_$1.json"
}

json_field() { grep -oE "\"$2\":[^,}]*" "$WORK/sub_$1.json" | head -1 | cut -d: -f2- | tr -d '"'; }

echo "H4G race test, run id $RUN, target $BASE_URL"

# ---------------------------------------------------------------------------
echo
echo "Test 1: two teams, different challenges, same instant -> exactly one First Blood"
# CSRF tokens are fetched before the race so that only the POSTs run concurrently.
for s in f1 f2; do
  register "rt${RUN}$s"; create_team "rt${RUN}$s" "RACETEST $RUN $s"
  csrf_from "rt${RUN}$s" "category.php?cat=$A_CAT" > "$WORK/rt${RUN}$s.tok"
done

submit_req "rt${RUN}f1" "$A_CAT" "$A_PROBLEM" "$A_FLAG" "$WORK/rt${RUN}f1.tok" &
submit_req "rt${RUN}f2" "$B_CAT" "$B_PROBLEM" "$B_FLAG" "$WORK/rt${RUN}f2.tok" &
wait

fb_count=0
for s in f1 f2; do
  u="rt${RUN}$s"
  [ "$(json_field "$u" correct)" = true ] || bad "$u: submission not accepted: $(json_field "$u" message)"
  [ "$(json_field "$u" first_blood)" = true ] && fb_count=$((fb_count + 1))
  echo "    $u -> awarded=$(json_field "$u" awarded) rank=$(json_field "$u" rank) first_blood=$(json_field "$u" first_blood)"
done
if   [ "$fb_count" = 1 ]; then ok "exactly one First Blood paid"
elif [ "$fb_count" = 0 ]; then bad "no First Blood paid: it was probably already claimed on this DB (DELETE FROM event_first_blood; then rerun)"
else bad "First Blood paid $fb_count times"; fi

# ---------------------------------------------------------------------------
echo
echo "Test 2: $SAME_TEAMS teams, same challenge, same instant -> distinct solve ranks"
users=()
for i in $(seq 1 "$SAME_TEAMS"); do
  u="rt${RUN}s$i"; users+=("$u")
  register "$u"; create_team "$u" "RACETEST $RUN s$i"
  csrf_from "$u" "category.php?cat=$A_CAT" > "$WORK/$u.tok"
done
for u in "${users[@]}"; do submit_req "$u" "$A_CAT" "$A_PROBLEM" "$A_FLAG" "$WORK/$u.tok" & done
wait

ranks=()
for u in "${users[@]}"; do
  r="$(json_field "$u" rank)"
  echo "    $u -> awarded=$(json_field "$u" awarded) rank=$r first_blood=$(json_field "$u" first_blood)"
  [ "$(json_field "$u" correct)" = true ] && [ -n "$r" ] && [ "$r" != null ] || bad "$u: no rank: $(json_field "$u" message)"
  [ "$(json_field "$u" first_blood)" = true ] && bad "$u got a second First Blood"
  ranks+=("$r")
done
dupes="$(printf '%s\n' "${ranks[@]}" | sort | uniq -d)"
missing="$(printf '%s\n' "${ranks[@]}" | grep -cvE '^[0-9]+$')"
if   [ "$missing" != 0 ]; then bad "$missing submission(s) got no rank, so distinct ranks are not proven"
elif [ -n "$dupes" ];      then bad "duplicate ranks: $dupes"
else ok "ranks are distinct: ${ranks[*]}"; fi

# ---------------------------------------------------------------------------
echo
echo "Test 3: two users join the last slot of a 3-member team at the same instant -> exactly one succeeds"
cap="rt${RUN}c0"
register "$cap"; create_team "$cap" "RACETEST $RUN cap"
code="$(invite_code "$cap")"
[ -n "$code" ] || die "could not read invite code from team.php"
for s in c1 c2; do
  u="rt${RUN}$s"; register "$u"; csrf_from "$u" team-setup.php > "$WORK/$u.tok"; join_req "$u" "$code" "$WORK/$u.tok"
  [ "$(cat "$WORK/join_$u.code")" = 303 ] || die "setup join for $u failed"
done
for s in j1 j2; do u="rt${RUN}$s"; register "$u"; csrf_from "$u" team-setup.php > "$WORK/$u.tok"; done

join_req "rt${RUN}j1" "$code" "$WORK/rt${RUN}j1.tok" &
join_req "rt${RUN}j2" "$code" "$WORK/rt${RUN}j2.tok" &
wait

wins=0; full=0
for s in j1 j2; do
  u="rt${RUN}$s"; c="$(cat "$WORK/join_$u.code")"
  echo "    $u -> HTTP $c"
  [ "$c" = 303 ] && wins=$((wins + 1))
  grep -q 'TEAM ALREADY HAS 4 MEMBERS' "$WORK/join_$u.body" && full=$((full + 1))
done
[ "$wins" = 1 ] && [ "$full" = 1 ] && ok "one join succeeded, one got 'TEAM ALREADY HAS 4 MEMBERS'" \
  || bad "expected 1 success + 1 full error, got $wins success(es) and $full full error(s)"

# ---------------------------------------------------------------------------
echo
if [ "$FAILS" = 0 ]; then echo "ALL RACE TESTS PASSED"; else echo "$FAILS CHECK(S) FAILED"; fi
echo
echo "Now run tests/verify.sql against the same DB to check teams.points = SUM(points_awarded)."
echo "Clean up this run's data with:"
echo "  DELETE FROM teams   WHERE name LIKE 'RACETEST $RUN %';"
echo "  DELETE FROM players WHERE username LIKE 'rt${RUN}%';"
echo "  DELETE FROM event_first_blood WHERE team_name LIKE 'RACETEST $RUN %';"
exit $(( FAILS > 0 ))
