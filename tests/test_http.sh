#!/bin/sh
#
# HTTP tests against a running install. Read-only: GETs only, no writes.
#
#   BASE_URL=http://localhost/rash ./tests/run.sh http
#
# 🔴 Three of these are only meaningful IMMEDIATELY AFTER A RESTART. The
# compile-time deprecation they check for is emitted once per compile, so with
# opcache warm they pass regardless. Restart PHP, then run.

set -u
BASE=${BASE_URL:-http://localhost}
DOCROOT=${DOCROOT:-$(cd "$(dirname "$0")/.." && pwd)}
pass=0; fail=0

req() { curl -s -o "$2" -w '%{http_code}' -H 'Cache-Control: no-cache' --max-time 10 "$BASE$1" 2>/dev/null; }
ok()  { pass=$((pass+1)); echo "    ok   $1"; }
bad() { fail=$((fail+1)); echo "    FAIL $1  ($2)"; }

echo
echo "  positive control -- the app is up"
# Without this, a dead install passes every "must not be served" check below.
b=$(mktemp); c=$(req "/" "$b")
if [ "$c" = "200" ]; then ok "/ responds 200"; else bad "/ responds 200" "HTTP $c"; fi
rm -f "$b"

echo
echo "  no route may leak a PHP diagnostic into the response"
# Routes taken from index.php's own switch($page[0]) rather than hand-picked.
for r in "" "?top" "?latest" "?random" "?browse" "?bottom" "?queue" "?login" \
         "?register" "?admin" "?logout" "?add" "?flag" "?change_pw" "?search" "?rss" "?1"; do
    b=$(mktemp); c=$(req "/$r" "$b")
    if grep -qiE '<b>(Deprecated|Warning|Notice|Fatal error)</b>' "$b"; then
        bad "${r:-/} clean" "$(grep -oiE '<b>[A-Za-z ]+</b>[^<]*' "$b" | head -1)"
    elif [ "$c" = "500" ]; then
        bad "${r:-/} clean" "HTTP 500"
    else
        ok "${r:-/} clean (HTTP $c)"
    fi
    rm -f "$b"
done

echo
echo "  every API command returns JSON, never a PHP diagnostic"
for cmd in napproved npending last random get latest search queue approve add vote flag nosuchcmd; do
    b=$(mktemp); c=$(req "/api.php?cmd=$cmd" "$b")
    if grep -qiE '<b>(Deprecated|Warning|Notice|Fatal error)</b>' "$b"; then
        bad "api cmd=$cmd clean" "$(head -c 80 "$b" | tr '\n' ' ')"
    else
        ok "api cmd=$cmd clean (HTTP $c)"
    fi
    rm -f "$b"
done

echo
echo "  API behaviour"
b=$(mktemp); req "/api.php?cmd=search&pattern=zzzzznomatchzzzzz" "$b" >/dev/null
if [ "$(tr -d ' \n' < "$b")" = "[]" ]; then
    ok "an empty result set returns [] rather than a warning"
else
    bad "an empty result set returns []" "got $(head -c 60 "$b")"
fi
rm -f "$b"

# main() merges $_REQUEST over $CONFIG, so a request parameter must not be
# able to lower an auth requirement.
b=$(mktemp); req "/api.php?cmd=queue&public_queue=1" "$b" >/dev/null
if grep -qi 'Insufficient authentication level' "$b"; then
    ok "?public_queue=1 cannot downgrade the queue auth level"
else
    bad "?public_queue=1 cannot downgrade the queue auth level" "got $(head -c 80 "$b" | tr '\n' ' ')"
fi
rm -f "$b"

echo
echo "  the checkout must not be served"
for p in "/.git/config" "/.git/HEAD" "/install.sql" "/tests/harness.php"; do
    b=$(mktemp); c=$(req "$p" "$b")
    if [ "$c" = "200" ]; then bad "$p is not served" "HTTP 200"; else ok "$p is not served (HTTP $c)"; fi
    rm -f "$b"
done

echo
echo "  ------------------------------------------------------------"
echo "  $pass passed, $fail failed"
echo
[ "$fail" -eq 0 ]
