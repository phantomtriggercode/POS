#!/usr/bin/env bash
# End-to-end smoke test: installs on SQLite via the web installer, signs in, uses every page.
# Run from the project root: bash tests/http_smoke.sh
# Against MySQL/MariaDB (database must exist and be EMPTY): POS_TEST_MYSQL="dbname;user;password" bash tests/http_smoke.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
PORT=${PORT:-8765}
BASE="http://127.0.0.1:$PORT"
export POS_CONFIG="$TMP/config.php"
JAR="$TMP/cookies"
cleanup() { kill "$SERVER" 2>/dev/null || true; rm -rf "$TMP"; rm -f "$ROOT/storage/pos.sqlite"*; }
trap cleanup EXIT
rm -f "$ROOT/storage/pos.sqlite"*
: > "$ROOT/storage/logs/php-error.log"

php -S 127.0.0.1:$PORT -t "$ROOT/public" >"$TMP/server.log" 2>&1 & SERVER=$!
sleep 1

fail() { echo "FAIL: $*"; exit 1; }
token() { grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
get() { curl -s -b "$JAR" -c "$JAR" -o "$TMP/out" -w '%{http_code}' "$BASE/$1"; }
post() { local path=$1; shift; curl -s -b "$JAR" -c "$JAR" -o "$TMP/out" -w '%{http_code}' "$BASE/$path" "$@"; }

# Fresh install redirects to installer
code=$(get "index.php"); [ "$code" = 302 ] || fail "expected redirect to installer, got $code"
get "install.php" >/dev/null; T=$(token <"$TMP/out")
DBARGS=(-d driver=sqlite)
if [ -n "${POS_TEST_MYSQL:-}" ]; then
  IFS=';' read -r DBN DBU DBP <<<"$POS_TEST_MYSQL"
  DBARGS=(-d driver=mysql -d host=127.0.0.1 -d port=3306 --data-urlencode "name=$DBN" --data-urlencode "user=$DBU" --data-urlencode "pass=$DBP")
fi
code=$(post install.php --data-urlencode "_csrf=$T" "${DBARGS[@]}" -d company_name="Motambarea Farmers" -d company_location="Kumba" \
  -d admin_name="Test Admin" -d admin_user=admin -d admin_pass=supersecret)
grep -q 'Installation complete' "$TMP/out" || { cat "$TMP/out" | sed 's/<[^>]*>//g' | grep -v '^\s*$' | head -20; fail "install"; }
get "install.php" >/dev/null; grep -q 'Already installed' "$TMP/out" || fail "installer not locked"
echo "ok   installer"

# Login (bad then good)
get "index.php?p=login" >/dev/null; T=$(token <"$TMP/out")
post "index.php?p=login" --data-urlencode "_csrf=$T" -d username=admin -d password=wrong >/dev/null
grep -q 'Incorrect username' "$TMP/out" || fail "bad login accepted"
T=$(token <"$TMP/out")
code=$(post "index.php?p=login" --data-urlencode "_csrf=$T" -d username=admin -d password=supersecret)
[ "$code" = 302 ] || fail "login failed ($code)"
echo "ok   login"

# CSRF is enforced
post "index.php?p=prices" -d grade_id=1 -d price_per_kg=1500 -d effective_date=2020-01-01 -d effective_time=08:00 >/dev/null
get "index.php?p=prices" >/dev/null
grep -q 'No prices set yet' "$TMP/out" || fail "CSRF not enforced: price saved without token"
echo "ok   csrf"

get "index.php?p=prices" >/dev/null; T=$(token <"$TMP/out")
post "index.php?p=prices" --data-urlencode "_csrf=$T" -d grade_id=1 -d price_per_kg=1450 -d effective_date=2020-01-01 -d effective_time=08:00 -d is_public=1 >/dev/null
get "index.php?p=farmer_edit" >/dev/null; T=$(token <"$TMP/out")
code=$(post "index.php?p=farmer_edit" --data-urlencode "_csrf=$T" -d full_name="Ebot Ashu" -d phone="+237670000000" -d village=Kake -d gps_lat=4.63 -d gps_lng=9.44)
[ "$code" = 302 ] || fail "farmer create ($code)"
get "index.php?p=purchase_new&farmer_id=1" >/dev/null; T=$(token <"$TMP/out")
grep -q 'data-prices' "$TMP/out" || fail "purchase form"
code=$(post "index.php?p=purchase_new" --data-urlencode "_csrf=$T" -d farmer_id=1 -d grade_id=1 -d bags=3 -d gross_kg=180.5 -d tare_kg=2.5 -d payment_method=cash)
[ "$code" = 302 ] || { sed 's/<[^>]*>//g' "$TMP/out" | grep -i -m3 'flash\|error'; fail "purchase create ($code)"; }
get "index.php?p=purchase&id=1" >/dev/null
grep -q '258 100 XAF' "$TMP/out" || fail "receipt amount (178 kg × 1450)"
echo "ok   farmer → price → purchase → receipt"

for p in dashboard purchases farmers "farmer&id=1" advances advance_new stock dispatches dispatch_new prices reports users user_edit settings audit account "purchase_new" "purchases&export=csv" nonexistent; do
  code=$(get "index.php?p=$p")
  case "$p" in nonexistent) want=404;; *) want=200;; esac
  [ "$code" = "$want" ] || fail "page $p returned $code"
done
get "index.php?p=purchases&export=csv&from=2000-01-01" >/dev/null
grep -q 'Ebot Ashu' "$TMP/out" || fail "csv content"
echo "ok   all pages render"

code=$(curl -s -o "$TMP/out" -w '%{http_code}' -H 'Origin: https://motambeareafarmers.com' "$BASE/api/prices.php")
[ "$code" = 200 ] && grep -q '"price_per_kg": 1450' "$TMP/out" || fail "public price api"
echo "ok   public price api"

# Clerk permissions
get "index.php?p=user_edit" >/dev/null; T=$(token <"$TMP/out")
post "index.php?p=user_edit" --data-urlencode "_csrf=$T" -d full_name="Clerk One" -d username=clerk1 -d role=clerk -d password=clerkpass1 >/dev/null
get "index.php?p=dashboard" >/dev/null; T=$(token <"$TMP/out")
post "index.php?p=logout" --data-urlencode "_csrf=$T" >/dev/null
get "index.php?p=login" >/dev/null; T=$(token <"$TMP/out")
post "index.php?p=login" --data-urlencode "_csrf=$T" -d username=clerk1 -d password=clerkpass1 >/dev/null
for p in reports users settings dispatches advance_new; do
  code=$(get "index.php?p=$p"); [ "$code" = 403 ] || fail "clerk reached $p ($code)"
done
code=$(get "index.php?p=purchase_new"); [ "$code" = 200 ] || fail "clerk purchase page"
echo "ok   clerk restricted"

if [ -s "$ROOT/storage/logs/php-error.log" ]; then cat "$ROOT/storage/logs/php-error.log"; fail "PHP errors logged"; fi
grep -iE 'warning|notice|fatal|deprecated' "$TMP/server.log" && fail "PHP warnings in server log"
echo "ALL SMOKE TESTS PASSED"
