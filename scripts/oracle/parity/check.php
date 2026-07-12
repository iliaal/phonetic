<?php
/*
 * Commons Codec parity check.
 *
 * Compares this extension's output against golden.tsv, a frozen snapshot of
 * Apache Commons Codec 1.17.1 (the parity oracle) for the words in words.txt.
 * The golden was produced by scripts/oracle/Oracle.java against the SHA-256
 * pinned jar and only contains (word, mode) pairs where the two agree, so any
 * mismatch here means a real parity regression (a rule-data regen, a generator
 * change, or an encoder edit that drifted from Commons).
 *
 * Run:  php -d extension=modules/phonetic.so scripts/oracle/parity/check.php
 * Exits non-zero on any mismatch or if the golden is empty/unreadable.
 * See scripts/oracle/parity/README.md to regenerate after a deliberate change.
 */

if (!extension_loaded('phonetic')) {
    fwrite(STDERR, "phonetic extension not loaded\n");
    exit(2);
}

$golden = $argv[1] ?? __DIR__ . '/golden.tsv';
$lines = @file($golden, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "cannot read golden: $golden\n");
    exit(2);
}

/** Order-independent normalisation for the multi-token encoders. */
function norm_set(string $s): string
{
    $t = array_filter(explode('|', $s), static fn($x) => $x !== '');
    sort($t);
    return implode('|', $t);
}

/** This extension's output for one mode, normalised the same way the golden is. */
function phonetic_out(string $mode, string $w): ?string
{
    switch ($mode) {
        case 'dm':
            $r = double_metaphone($w);            // default maxlen 4
            return $r['primary'] . '|' . $r['alternate'];
        case 'nysiis_strict':
            return nysiis($w);                    // default 6 == strict
        case 'mra':
            return match_rating($w);
        case 'dmsoundex':
            return norm_set(implode('|', dm_soundex($w)));
        case 'bmpm_gen_approx':
            return norm_set(bmpm($w, BMPM_GENERIC, BMPM_APPROX));
        default:
            return null;
    }
}

$checked = 0;
$fail = 0;
foreach ($lines as $line) {
    if ($line === '') {
        continue;
    }
    [$mode, $w, $exp] = explode("\t", $line, 3);
    $got = phonetic_out($mode, $w);
    if ($got === null) {
        fwrite(STDERR, "unknown mode in golden: $mode\n");
        exit(2);
    }
    $checked++;
    if ($got !== $exp) {
        $fail++;
        fwrite(STDERR, "MISMATCH [$mode] \"$w\": commons=$exp phonetic=$got\n");
    }
}

if ($checked === 0) {
    fwrite(STDERR, "golden is empty — parity check is not effective\n");
    exit(2);
}

printf("parity: %d checked, %d mismatch(es)\n", $checked, $fail);
exit($fail === 0 ? 0 : 1);
