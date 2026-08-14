#!/bin/sh
#
# rash test suite.
#
#   ./tests/run.sh          unit + http
#   ./tests/run.sh unit     no network, no database
#   ./tests/run.sh http     against a running install (set BASE_URL)
#
# Uses the `php` on PATH; override with PHP_BIN=/path/to/php to test another
# version. Everything is read-only -- the suite makes no database writes and
# the HTTP half only issues GETs.

set -eu
ROOT=$(cd "$(dirname "$0")/.." && pwd)
PHP_BIN=${PHP_BIN:-php}
WHICH=${1:-all}
rc=0

case "$WHICH" in
    unit) "$PHP_BIN" "$ROOT/tests/run_unit.php" || rc=1 ;;
    http) sh "$ROOT/tests/test_http.sh" || rc=1 ;;
    all)  "$PHP_BIN" "$ROOT/tests/run_unit.php" || rc=1
          sh "$ROOT/tests/test_http.sh" || rc=1 ;;
    *)    echo "usage: $0 [unit|http|all]" >&2; exit 2 ;;
esac
exit $rc
