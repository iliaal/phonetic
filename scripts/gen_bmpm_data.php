<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2026, Ilia Alshanetsky                                 |
  | Copyright (c) 2026, Advanced Internet Designs Inc.                   |
  +----------------------------------------------------------------------+
  | This source file is subject to the BSD 3-Clause license that is      |
  | bundled with this package in the file LICENSE.                       |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

/*
 * Code generator: parses the vendored Apache Commons Codec Beider-Morse and
 * Daitch-Mokotoff rule files (vendor/commons-codec-bm/) into a self-contained
 * C header (src/bmpm_data.h) consumed by the phonetic engines.
 *
 * The parsers replicate the reference loaders in Commons Codec
 * (Rule.java, Languages.java, Lang.java, DaitchMokotoffSoundex.java) so the
 * generated tables carry exactly the data the upstream algorithm operates on.
 * Pattern / context / phoneme / code columns are kept RAW: the engine compiles
 * the regex and phoneme grammar later -- this script does not interpret them.
 */

error_reporting(E_ALL);

const NAME_TYPES = ['gen', 'ash', 'sep'];
const RULE_TYPES = ['rules', 'approx', 'exact'];

$root    = dirname(__DIR__);
$vendor  = $root . '/vendor/commons-codec-bm';
$bm_dir  = $vendor . '/bm';
$dm_file = $vendor . '/dmrules.txt';
$out     = $root . '/src/bmpm_data.h';

if (!is_dir($bm_dir)) {
    fwrite(STDERR, "error: vendored BM directory not found: $bm_dir\n");
    exit(1);
}

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

function fail(string $msg): void
{
    fwrite(STDERR, "error: $msg\n");
    exit(1);
}

/* Strip at most one leading and one trailing double quote (matches the
 * reference stripQuotes(): it does NOT unescape, so a literal-quote pattern
 * such as "\"" is retained verbatim as the two bytes \ and "). */
function strip_quotes(string $s): string
{
    if ($s !== '' && $s[0] === '"') {
        $s = substr($s, 1);
    }
    if ($s !== '' && substr($s, -1) === '"') {
        $s = substr($s, 0, -1);
    }
    return $s;
}

/* Emit a C string literal. Printable ASCII passes through; backslash, double
 * quote and question mark are escaped; everything else (control bytes and the
 * UTF-8 high bytes that pervade this data) becomes a 3-digit octal escape.
 * Octal escapes are bounded to 3 digits, so no greedy-escape ambiguity can
 * arise and the output is charset-independent and -Wall clean. */
function c_str(string $s): string
{
    $out = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = ord($s[$i]);
        if ($c === 0x5C) {            /* \  */
            $out .= '\\\\';
        } elseif ($c === 0x22) {      /* "  */
            $out .= '\\"';
        } elseif ($c === 0x3F) {      /* ?  -> avoid trigraphs */
            $out .= '\\?';
        } elseif ($c >= 0x20 && $c < 0x7F) {
            $out .= $s[$i];
        } else {
            $out .= sprintf('\\%03o', $c);
        }
    }
    return '"' . $out . '"';
}

/* Read a resource file and yield its logical content lines: multi-line /* *\/
 * blocks dropped, // line comments stripped, blank lines removed. The comment
 * state machine mirrors the reference loaders byte-for-byte. */
function logical_lines(string $path): array
{
    $raw_lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($raw_lines === false) {
        fail("cannot read $path");
    }
    $lines    = [];
    $in_block = false;
    foreach ($raw_lines as $raw) {
        if ($in_block) {
            if (str_ends_with($raw, '*/')) {
                $in_block = false;
            }
            continue;
        }
        if (str_starts_with($raw, '/*')) {
            $in_block = true;
            continue;
        }
        $line = $raw;
        $ci = strpos($line, '//');
        if ($ci !== false) {
            $line = substr($line, 0, $ci);
        }
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $lines[] = $line;
    }
    return $lines;
}

/* ---------------------------------------------------------------------------
 * BMPM rule files (Rule.java)
 * ------------------------------------------------------------------------- */

/* Parse a rule resource into an ordered list of [pattern, lcon, rcon, phoneme].
 * #include directives splice the named sibling file in place; a visited guard
 * prevents pathological include cycles. */
function parse_rule_file(string $name, string $bm_dir, array $stack = []): array
{
    $path = "$bm_dir/$name.txt";
    if (!is_file($path)) {
        fail("included resource not found: $path");
    }
    if (in_array($name, $stack, true)) {
        fail("include cycle detected at $name (" . implode('->', $stack) . ')');
    }
    $stack[] = $name;

    $rules = [];
    foreach (logical_lines($path) as $line) {
        if (str_starts_with($line, '#include')) {
            $incl = trim(substr($line, strlen('#include')));
            if ($incl === '' || str_contains($incl, ' ')) {
                fail("malformed include '$line' in $path");
            }
            foreach (parse_rule_file($incl, $bm_dir, $stack) as $r) {
                $rules[] = $r;
            }
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        if (count($parts) !== 4) {
            fail("malformed rule (" . count($parts) . " parts) '$line' in $path");
        }
        $rules[] = [
            strip_quotes($parts[0]),
            strip_quotes($parts[1]),
            strip_quotes($parts[2]),
            strip_quotes($parts[3]),
        ];
    }
    return $rules;
}

/* ---------------------------------------------------------------------------
 * Parse everything
 * ------------------------------------------------------------------------- */

$files_parsed = 0;

/* Per-name-type ordered language lists (xx_languages.txt). */
$languages = [];
foreach (NAME_TYPES as $nt) {
    $languages[$nt] = logical_lines("$bm_dir/{$nt}_languages.txt");
    $files_parsed++;
    if (!$languages[$nt]) {
        fail("no languages parsed for $nt");
    }
}

/*
 * Rule sets, keyed (name_type, rule_type, language). This mirrors the reference
 * static initializer exactly: every language in xx_languages.txt for each rule
 * type, plus a "common" set for approx/exact (never for rules). Helper files
 * such as xx_exact_approx_common and xx_hebrew_common are include-only and are
 * spliced where referenced, never loaded as standalone sets.
 */
$rulesets = [];   /* list of [nt, rt, lang, rules[]] */
foreach (NAME_TYPES as $nt) {
    foreach (RULE_TYPES as $rt) {
        foreach ($languages[$nt] as $lang) {
            $rulesets[] = [$nt, $rt, $lang, parse_rule_file("{$nt}_{$rt}_{$lang}", $bm_dir)];
            $files_parsed++;
        }
        if ($rt !== 'rules') {
            $rulesets[] = [$nt, $rt, 'common', parse_rule_file("{$nt}_{$rt}_common", $bm_dir)];
            $files_parsed++;
        }
    }
}

/* Language-guessing rules (xx_lang.txt): regex  lang1+lang2  true|false. */
$lang_rules = [];
foreach (NAME_TYPES as $nt) {
    $rows = [];
    foreach (logical_lines("$bm_dir/{$nt}_lang.txt") as $line) {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 3) {
            fail("malformed lang rule '$line' in {$nt}_lang.txt");
        }
        $rows[] = [$parts[0], $parts[1], $parts[2] === 'true'];
    }
    $lang_rules[$nt] = $rows;
    $files_parsed++;
}

/* Daitch-Mokotoff rules + ASCII foldings (dmrules.txt). */
$dm_rules    = [];
$dm_foldings = [];
foreach (logical_lines($dm_file) as $line) {
    if (str_contains($line, '=')) {
        $parts = explode('=', $line);
        if (count($parts) !== 2) {
            fail("malformed folding '$line' in dmrules.txt");
        }
        $dm_foldings[] = [$parts[0], $parts[1]];
    } else {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) !== 4) {
            fail("malformed DM rule '$line' in dmrules.txt");
        }
        $dm_rules[] = [
            strip_quotes($parts[0]),
            strip_quotes($parts[1]),
            strip_quotes($parts[2]),
            strip_quotes($parts[3]),
        ];
    }
}
$files_parsed++;

/* ---------------------------------------------------------------------------
 * Emit src/bmpm_data.h
 * ------------------------------------------------------------------------- */

$total_rules = 0;
foreach ($rulesets as $rs) {
    $total_rules += count($rs[3]);
}

$b = [];
$b[] = '/*';
$b[] = '  +----------------------------------------------------------------------+';
$b[] = '  | Copyright (c) 2026, Ilia Alshanetsky                                 |';
$b[] = '  | Copyright (c) 2026, Advanced Internet Designs Inc.                   |';
$b[] = '  +----------------------------------------------------------------------+';
$b[] = '  | This source file is subject to the BSD 3-Clause license that is      |';
$b[] = '  | bundled with this package in the file LICENSE.                       |';
$b[] = '  +----------------------------------------------------------------------+';
$b[] = '  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |';
$b[] = '  +----------------------------------------------------------------------+';
$b[] = '*/';
$b[] = '';
$b[] = '/*';
$b[] = ' * GENERATED FILE -- DO NOT EDIT BY HAND.';
$b[] = ' *';
$b[] = ' * Regenerate with:  php scripts/gen_bmpm_data.php';
$b[] = ' *';
$b[] = ' * Source data: Beider-Morse and Daitch-Mokotoff rule files vendored from';
$b[] = ' * Apache Commons Codec (Apache License 2.0) under vendor/commons-codec-bm/.';
$b[] = ' * Pattern / context / phoneme / code columns are stored RAW (uninterpreted);';
$b[] = ' * the phonetic engines compile the regex and phoneme grammar at runtime.';
$b[] = ' *';
$b[] = ' * Schema';
$b[] = ' * ------';
$b[] = ' *   bmpm_rule        : one Beider-Morse transliteration rule. Fields are the';
$b[] = ' *                      four raw columns pattern / lcontext / rcontext / phoneme.';
$b[] = ' *   bmpm_ruleset     : a named rule array = the rules of one resource file with';
$b[] = ' *                      #include directives spliced in. Keyed by (name_type,';
$b[] = ' *                      rule_type, language). rules == NULL && count == 0 marks an';
$b[] = ' *                      intentionally empty resource (e.g. gen_exact_hebrew).';
$b[] = ' *   bmpm_rulesets[]  : the lookup table over every ruleset; scan it for a';
$b[] = ' *                      (name_type, rule_type, language) triple.';
$b[] = ' *   bmpm_lang_rule   : one language-guessing rule from xx_lang.txt: a regex, the';
$b[] = ' *                      raw "+"-joined language set it implies, and accept flag.';
$b[] = ' *   bmpm_lang_set[]  : per-name-type language-guessing rule lists.';
$b[] = ' *   bmpm_languages_* : per-name-type ordered language lists (xx_languages.txt);';
$b[] = ' *                      order is significant and matches upstream.';
$b[] = ' *   dm_rule          : one Daitch-Mokotoff rule: pattern + three code columns';
$b[] = ' *                      (at_start / before_vowel / default). A code column may';
$b[] = ' *                      hold "|"-separated branch alternatives, kept raw.';
$b[] = ' *   dm_folding       : one accent/ligature folding (from -> to), raw UTF-8.';
$b[] = ' *';
$b[] = ' * name_type values : BMPM_GEN, BMPM_ASH, BMPM_SEP (indices into bmpm_name_types).';
$b[] = ' * rule_type values : BMPM_RULES, BMPM_APPROX, BMPM_EXACT.';
$b[] = ' */';
$b[] = '';
$b[] = '#ifndef PHP_BMPM_DATA_H';
$b[] = '#define PHP_BMPM_DATA_H';
$b[] = '';
$b[] = '#include <stddef.h>';
$b[] = '';
$b[] = 'enum bmpm_name_type { BMPM_GEN = 0, BMPM_ASH = 1, BMPM_SEP = 2 };';
$b[] = 'enum bmpm_rule_type { BMPM_RULES = 0, BMPM_APPROX = 1, BMPM_EXACT = 2 };';
$b[] = '';
$b[] = 'static const char *const bmpm_name_types[] = { "gen", "ash", "sep" };';
$b[] = 'static const char *const bmpm_rule_types[] = { "rules", "approx", "exact" };';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    const char *pattern;';
$b[] = '    const char *lcontext;';
$b[] = '    const char *rcontext;';
$b[] = '    const char *phoneme;';
$b[] = '} bmpm_rule;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    int               name_type;';
$b[] = '    int               rule_type;';
$b[] = '    const char       *language;';
$b[] = '    const bmpm_rule  *rules;';
$b[] = '    size_t            count;';
$b[] = '} bmpm_ruleset;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    const char *pattern;';
$b[] = '    const char *languages;';
$b[] = '    int         accept;';
$b[] = '} bmpm_lang_rule;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    int                   name_type;';
$b[] = '    const bmpm_lang_rule *rules;';
$b[] = '    size_t                count;';
$b[] = '} bmpm_lang_set;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    int                 name_type;';
$b[] = '    const char *const  *languages;';
$b[] = '    size_t              count;';
$b[] = '} bmpm_language_list;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    const char *pattern;';
$b[] = '    const char *at_start;';
$b[] = '    const char *before_vowel;';
$b[] = '    const char *default_code;';
$b[] = '} dm_rule;';
$b[] = '';
$b[] = 'typedef struct {';
$b[] = '    const char *from;';
$b[] = '    const char *to;';
$b[] = '} dm_folding;';
$b[] = '';

$nt_enum = ['gen' => 'BMPM_GEN', 'ash' => 'BMPM_ASH', 'sep' => 'BMPM_SEP'];
$rt_enum = ['rules' => 'BMPM_RULES', 'approx' => 'BMPM_APPROX', 'exact' => 'BMPM_EXACT'];

/* Per-ruleset rule arrays. */
$b[] = '/* ---- Beider-Morse rule arrays (one per resource, #includes spliced) ---- */';
$b[] = '';
$table = [];
foreach ($rulesets as $rs) {
    [$nt, $rt, $lang, $rules] = $rs;
    $ident = "bmpm_rules_{$nt}_{$rt}_{$lang}";
    $count = count($rules);
    if ($count === 0) {
        $table[] = sprintf('    { %s, %s, %s, NULL, 0 },',
            $nt_enum[$nt], $rt_enum[$rt], c_str($lang));
        continue;
    }
    $b[] = "static const bmpm_rule {$ident}[] = {";
    foreach ($rules as $r) {
        $b[] = sprintf('    { %s, %s, %s, %s },',
            c_str($r[0]), c_str($r[1]), c_str($r[2]), c_str($r[3]));
    }
    $b[] = '};';
    $b[] = '';
    $table[] = sprintf('    { %s, %s, %s, %s, %d },',
        $nt_enum[$nt], $rt_enum[$rt], c_str($lang), $ident, $count);
}

$b[] = '/* ---- Ruleset lookup table: scan for (name_type, rule_type, language) ---- */';
$b[] = 'static const bmpm_ruleset bmpm_rulesets[] = {';
foreach ($table as $row) {
    $b[] = $row;
}
$b[] = '};';
$b[] = 'static const size_t bmpm_rulesets_count = ' . count($table) . ';';
$b[] = '';

/* Per-name-type language lists. */
$b[] = '/* ---- Per-name-type ordered language lists (order is significant) ---- */';
$b[] = '';
$lang_list_table = [];
foreach (NAME_TYPES as $nt) {
    $ident = "bmpm_languages_{$nt}";
    $b[] = "static const char *const {$ident}[] = {";
    foreach ($languages[$nt] as $lang) {
        $b[] = '    ' . c_str($lang) . ',';
    }
    $b[] = '};';
    $b[] = '';
    $lang_list_table[] = sprintf('    { %s, %s, %d },',
        $nt_enum[$nt], $ident, count($languages[$nt]));
}
$b[] = 'static const bmpm_language_list bmpm_language_lists[] = {';
foreach ($lang_list_table as $row) {
    $b[] = $row;
}
$b[] = '};';
$b[] = 'static const size_t bmpm_language_lists_count = ' . count($lang_list_table) . ';';
$b[] = '';

/* Language-guessing rules. */
$b[] = '/* ---- Per-name-type language-guessing rules (xx_lang.txt) ---- */';
$b[] = '';
$lang_set_table = [];
foreach (NAME_TYPES as $nt) {
    $ident = "bmpm_lang_{$nt}";
    $b[] = "static const bmpm_lang_rule {$ident}[] = {";
    foreach ($lang_rules[$nt] as $row) {
        $b[] = sprintf('    { %s, %s, %d },',
            c_str($row[0]), c_str($row[1]), $row[2] ? 1 : 0);
    }
    $b[] = '};';
    $b[] = '';
    $lang_set_table[] = sprintf('    { %s, %s, %d },',
        $nt_enum[$nt], $ident, count($lang_rules[$nt]));
}
$b[] = 'static const bmpm_lang_set bmpm_lang_sets[] = {';
foreach ($lang_set_table as $row) {
    $b[] = $row;
}
$b[] = '};';
$b[] = 'static const size_t bmpm_lang_sets_count = ' . count($lang_set_table) . ';';
$b[] = '';

/* Daitch-Mokotoff. */
$b[] = '/* ---- Daitch-Mokotoff rules (dmrules.txt) ---- */';
$b[] = 'static const dm_rule dm_rules[] = {';
foreach ($dm_rules as $r) {
    $b[] = sprintf('    { %s, %s, %s, %s },',
        c_str($r[0]), c_str($r[1]), c_str($r[2]), c_str($r[3]));
}
$b[] = '};';
$b[] = 'static const size_t dm_rules_count = ' . count($dm_rules) . ';';
$b[] = '';
$b[] = '/* ---- Daitch-Mokotoff accent / ligature foldings ---- */';
$b[] = 'static const dm_folding dm_foldings[] = {';
foreach ($dm_foldings as $f) {
    $b[] = sprintf('    { %s, %s },', c_str($f[0]), c_str($f[1]));
}
$b[] = '};';
$b[] = 'static const size_t dm_foldings_count = ' . count($dm_foldings) . ';';
$b[] = '';
$b[] = '#endif /* PHP_BMPM_DATA_H */';
$b[] = '';

if (file_put_contents($out, implode("\n", $b)) === false) {
    fail("could not write $out");
}

/* ---------------------------------------------------------------------------
 * Stats
 * ------------------------------------------------------------------------- */

fwrite(STDOUT, "Generated $out\n");
fwrite(STDOUT, sprintf("  resource files parsed : %d\n", $files_parsed));
fwrite(STDOUT, sprintf("  BMPM rulesets         : %d\n", count($rulesets)));
fwrite(STDOUT, sprintf("  BMPM rules (total)    : %d\n", $total_rules));
foreach (NAME_TYPES as $nt) {
    fwrite(STDOUT, sprintf("  languages[%s]         : %d\n", $nt, count($languages[$nt])));
}
foreach (NAME_TYPES as $nt) {
    fwrite(STDOUT, sprintf("  lang-guess rules[%s]  : %d\n", $nt, count($lang_rules[$nt])));
}
fwrite(STDOUT, sprintf("  DM rules              : %d\n", count($dm_rules)));
fwrite(STDOUT, sprintf("  DM foldings           : %d\n", count($dm_foldings)));
