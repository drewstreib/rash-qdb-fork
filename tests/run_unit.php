<?php
/*
 * Unit test entry point -- no network, no database.
 *
 * Not covered here: anything in api.php or index.php. Both require
 * settings.php and a live PDO handle at load time, so neither can be included
 * in isolation without a refactor. tests/test_http.sh covers those paths
 * against a running install instead.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/harness.php';
echo "rash unit tests (PHP " . PHP_VERSION . ")\n";
require __DIR__ . '/test_util_funcs.php';
require __DIR__ . '/test_source.php';
exit(T::summary());
