<?php
/*
 * Minimal dependency-free test harness.
 *
 * No composer, no PHPUnit -- deliberately. rash has no autoloader and no
 * dependency manager, so a suite needing `composer install` could not be run
 * from a plain checkout, which is how rash is deployed.
 *
 * Runs on PHP 7.3 through 8.5.
 */

class T
{
    public static $pass = 0;
    public static $fail = 0;
    public static $defects = 0;
    public static $failures = array();
    private static $group = '';

    public static function group($name)
    {
        self::$group = $name;
        echo "\n  $name\n";
    }

    public static function ok($cond, $name, $detail = '')
    {
        if ($cond) {
            self::$pass++;
            echo "    ok   $name\n";
        } else {
            self::$fail++;
            self::$failures[] = self::$group . ' / ' . $name . ($detail ? "  ($detail)" : '');
            echo "    FAIL $name" . ($detail ? "  ($detail)" : '') . "\n";
        }
    }

    public static function eq($expected, $actual, $name)
    {
        self::ok($expected === $actual, $name,
                 'expected ' . self::show($expected) . ', got ' . self::show($actual));
    }

    public static function notContains($needle, $haystack, $name)
    {
        self::ok(strpos($haystack, $needle) === false, $name,
                 self::show($haystack) . ' contains ' . self::show($needle));
    }

    /*
     * A known defect: asserted so it is visible and so we are told the day it
     * changes, but it does not fail the suite. Fixing any of these is a
     * behaviour change and needs its own decision.
     */
    public static function defect($cond, $name, $why)
    {
        self::$defects++;
        $state = $cond ? 'STILL PRESENT' : 'GONE -- update the suite';
        echo "    defect $state: $name\n           $why\n";
    }

    private static function show($v)
    {
        if (is_string($v)) return '"' . $v . '"';
        if (is_null($v))   return 'null';
        if (is_bool($v))   return $v ? 'true' : 'false';
        if (is_array($v))  return 'array(' . count($v) . ')';
        return (string)$v;
    }

    public static function summary()
    {
        echo "\n" . str_repeat('-', 62) . "\n";
        echo sprintf("  %d passed, %d failed, %d known defects\n",
                     self::$pass, self::$fail, self::$defects);
        if (self::$failures) {
            echo "\n  Failures:\n";
            foreach (self::$failures as $f) echo "    - $f\n";
        }
        echo "\n";
        return self::$fail === 0 ? 0 : 1;
    }
}
