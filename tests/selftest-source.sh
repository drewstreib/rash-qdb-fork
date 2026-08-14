#!/bin/sh
#
# Falsification harness for tests/test_source.php.
#
# 🔴 This is the point of the source tests, not an extra.
#
# A tree-wide "no matches" assertion is worthless until it has been shown to
# match something -- a wrong pattern and a clean tree produce the same
# confident zero. So: copy the tree, inject each defect the source tests claim
# to catch, and require the suite to FAIL on each one.
#
# Every injected defect is one that was really present in this codebase.

set -u
ROOT=$(cd "$(dirname "$0")/.." && pwd)
PHP_BIN=${PHP_BIN:-php}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
pass=0; fail=0

run_suite() {
    RASH_SRC="$1" "$PHP_BIN" -r '
        require getenv("H") . "/harness.php";
        require getenv("H") . "/test_source.php";
        exit(T::$fail === 0 ? 0 : 1);
    ' >/dev/null 2>&1
}

expect_caught() {
    label=$1; inject=$2
    rm -rf "$WORK/src"; mkdir -p "$WORK/src"
    ( cd "$ROOT" && tar cf - --exclude=.git . ) | ( cd "$WORK/src" && tar xf - )
    ( cd "$WORK/src" && eval "$inject" )
    if H="$ROOT/tests" run_suite "$WORK/src"; then
        fail=$((fail+1)); echo "    FAIL  NOT caught: $label"
    else
        pass=$((pass+1)); echo "    ok    caught: $label"
    fi
}

echo
echo "  baseline -- the real tree must PASS"
rm -rf "$WORK/src"; mkdir -p "$WORK/src"
( cd "$ROOT" && tar cf - --exclude=.git . ) | ( cd "$WORK/src" && tar xf - )
if H="$ROOT/tests" run_suite "$WORK/src"; then
    pass=$((pass+1)); echo "    ok    real tree passes"
else
    fail=$((fail+1)); echo "    FAIL  real tree does NOT pass"
fi

echo
echo "  each injected defect must be CAUGHT"
expect_caught "display_errors On" \
  "printf '%s\n' \"<?php ini_set('display_errors','On');\" > injected.php"
expect_caught "curly-brace string offset" \
  "printf '%s\n' '<?php \$s=\"abc\"; echo \$s{0};' > injected.php"
expect_caught "\${var} interpolation" \
  "printf '%s\n' '<?php \$x=1; echo \"v \${x}\";' > injected.php"
expect_caught "each() -- removed in 8.0" \
  "printf '%s\n' '<?php \$a=[1]; while (list(\$k,\$v)=each(\$a)) {}' > injected.php"
expect_caught "create_function() -- removed in 8.0" \
  "printf '%s\n' '<?php \$f=create_function(\"\\\$a\",\"return \\\$a;\");' > injected.php"
expect_caught "manual mt_srand()" \
  "printf '%s\n' '<?php mt_srand(microtime(true));' > injected.php"
expect_caught "session_unset() with an argument" \
  "printf '%s\n' '<?php session_unset(\$_SESSION[\"x\"]);' > injected.php"
expect_caught "superglobal loosely compared to a number" \
  "printf '%s\n' '<?php if (\$_GET[\"x\"] == 0) { echo 1; }' > injected.php"

echo
echo "  ------------------------------------------------------------"
echo "  $pass passed, $fail failed"
echo
[ "$fail" -eq 0 ]
