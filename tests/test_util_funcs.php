<?php
/*
 * Unit tests for the pure helpers in util_funcs.php.
 *
 * util_funcs.php is safe to require in isolation: it defines functions only,
 * has no top-level side effects and includes nothing.
 */

require_once __DIR__ . '/../util_funcs.php';

T::group('get_number_limit()');
T::eq(5,  get_number_limit('5', 1, 10),   'accepts an in-range value');
T::eq(1,  get_number_limit('0', 1, 10),   'clamps below the minimum');
T::eq(10, get_number_limit('99', 1, 10),  'clamps above the maximum');
T::eq(10, get_number_limit('abc', 1, 10), 'non-numeric falls back to the maximum');
T::eq(10, get_number_limit(null, 1, 10),  'null falls back to the maximum');
T::eq(10, get_number_limit('-3', 1, 10),  'a negative fails the digit test, so falls back to max');

T::group('mangle_quote_text()');
$m = mangle_quote_text("see http://example.com/x for more");
T::ok(strpos($m, '<A href="http://example.com/x">') !== false, 'turns a bare URL into an anchor');
T::eq("a<br />\nb", mangle_quote_text("a\nb"), 'converts a newline to <br />');
T::eq('', mangle_quote_text(''), 'handles the empty string');

T::group('str_rand()');
/* Generates password salts at four call sites, so a stuck seed would be a
   real problem rather than a cosmetic one. */
T::eq(8, strlen(str_rand()), 'default length is 8');
T::eq(4, strlen(str_rand(4)), 'honours an explicit length');
$custom = str_rand(12, 'ab');
T::eq(12, strlen($custom), 'honours an explicit length with a custom alphabet');
T::ok(preg_match('/^[ab]{12}$/', $custom) === 1, 'draws only from the given alphabet');
$seen = array();
for ($i = 0; $i < 200; $i++) $seen[str_rand()] = 1;
T::ok(count($seen) === 200, '200 calls give 200 distinct values',
      'a stuck or repeated seed collapses this');

T::group('urlargs()');
T::eq('a', urlargs('a'), 'one argument passes through');
$two = urlargs('a', 'b');
T::ok(strpos($two, 'a') === 0 && substr($two, -1) === 'b', 'two arguments are joined in order');
$three = urlargs('a', 'b', 'c');
T::ok(strpos($three, 'a') === 0 && substr($three, -1) === 'c', 'three arguments are joined in order');

T::group('db_tablename()');
T::eq('quotes', db_tablename('quotes'), 'returns the bare name when no prefix applies');

T::defect(db_tablename('quotes') === 'quotes',
          'db_tablename() cannot apply $CONFIG[db_table_prefix]',
          '$CONFIG is read without a `global` declaration, so the isset() is '
          . 'always false and the setting is unreachable. Not fixed here: it '
          . 'would rename every table at once for anyone who had set it.');
