<?php
/*
 * Tree-wide source invariants.
 *
 * Every assertion here scans the WHOLE tree and names the offending files,
 * rather than checking a file someone already had open. That is deliberate:
 * the defects this suite was written against were all per-file properties
 * that had been fixed in one place and missed in another -- display_errors
 * was corrected in index.php while api.php kept it for another hour.
 *
 * A new .php file is therefore covered the moment it lands.
 */

function src_files()
{
    /* RASH_SRC lets tests/selftest-source.sh point this at a deliberately
       broken copy. Without it these assertions could never be shown capable
       of failing. */
    $root = realpath(getenv('RASH_SRC') ?: __DIR__ . '/..');
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        $p = $f->getPathname();
        if (strpos($p, '/.git/') !== false) continue;
        if ($f->isFile() && substr($f->getFilename(), -4) === '.php') $out[] = $p;
    }
    sort($out);
    return $out;
}

function rel($p) { return str_replace(realpath(getenv('RASH_SRC') ?: __DIR__ . '/..') . '/', '', $p); }

/*
 * Source with comments blanked out, line numbers preserved.
 *
 * Needed because commented-out code is not a defect: install.php carries a
 * disabled ini_set('display_errors','On') inside a block comment, and a plain
 * regex reports it. A check that cries wolf gets ignored, so this strips
 * comments with the tokenizer rather than guessing at them. Strings are NOT
 * stripped -- ${var} interpolation is a string, and it is one of the things
 * being looked for.
 */
function strip_comments($src)
{
    $out = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                $out .= str_repeat("\n", substr_count($t[1], "\n"));  /* keep line numbers */
                continue;
            }
            $out .= $t[1];
        } else {
            $out .= $t;
        }
    }
    return $out;
}

function no_file_matches($re, $name, $why)
{
    $bad = array();
    foreach (src_files() as $f) {
        if (strpos($f, '/tests/') !== false) continue;   /* the suite itself quotes these patterns */
        $body = strip_comments(file_get_contents($f));
        if (preg_match_all($re, $body, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $bad[] = rel($f) . ':' . (substr_count(substr($body, 0, $hit[1]), "\n") + 1);
            }
        }
    }
    T::ok(empty($bad), $name, $bad ? implode(', ', array_slice($bad, 0, 6)) . ($why ? " -- $why" : '') : '');
}

$files = src_files();
T::group('source scan covers the tree');
T::ok(count($files) >= 8, 'found the PHP files to scan', count($files) . ' files');
$saw = 0;
foreach ($files as $f) { if (strpos(file_get_contents($f), '<?php') !== false) $saw++; }
T::ok($saw >= 8, 'scanner can read file contents (control)', "$saw files contained <?php");

T::group('no entry point may display errors to the visitor');
/* A runtime ini_set() overrides php.ini, so a server-level setting does not
   make this safe -- the call must not be there. */
no_file_matches('/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?(?:1|On|on|true|TRUE)[\'"]?\s*\)/',
                'no file turns display_errors ON', 'a runtime ini_set overrides php.ini');

T::group('removed and deprecated PHP syntax');
no_file_matches('/\$[A-Za-z_][A-Za-z0-9_]*\{[^}]*\}\s*[;,.\)]/',
                'no curly-brace string offsets ($s{0})', 'removed in PHP 8.0');
no_file_matches('/"[^"\n]*\$\{[A-Za-z_]/',
                'no ${var} string interpolation', 'deprecated 8.2; emitted at compile time');
no_file_matches('/(?<![A-Za-z0-9_>$])(each|create_function|money_format|get_magic_quotes_gpc|convert_cyr_string)\s*\(/',
                'no functions removed in PHP 8.0', '');
no_file_matches('/mt_srand\s*\(/',
                'mt_rand is not manually seeded', 'auto-seeded since 7.1; a float seed is an 8.1 deprecation');
no_file_matches('/session_unset\s*\(\s*[^)\s]/',
                'session_unset() is called with no arguments', 'ArgumentCountError on PHP 8.0');

T::group('PHP 8 comparison semantics');
/* PHP 8.0 changed exactly one case: number == non-numeric-string. Previously
   the string became a number ("foo" -> 0, so 0 == "foo" was TRUE); now the
   number becomes a string, so it is FALSE. Only a loose == against something
   a client controls is exposed. */
no_file_matches('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)\s*\[[^\]]*\]\s*[!=]=\s*-?\d/',
                'no superglobal is loosely compared to a number', 'the one case PHP 8.0 changed');
