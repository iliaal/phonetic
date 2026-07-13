<?php
/*
 * Commons Codec parity check.
 *
 * Compares this extension's output against golden.tsv, a frozen snapshot of
 * Apache Commons Codec 1.17.1 for every configured mode/word pair. The checker
 * rejects incomplete, duplicate, and unexpected fixture rows before treating
 * parity as successful.
 */

const PARITY_MODES = [
    'dm',
    'nysiis_strict',
    'nysiis_full',
    'mra',
    'dmsoundex',
    'bmpm_gen_approx',
    'bmpm_gen_exact',
    'bmpm_ash_approx',
    'bmpm_ash_exact',
    'bmpm_sep_approx',
    'bmpm_sep_exact',
];

if (!extension_loaded('phonetic')) {
    fwrite(STDERR, "phonetic extension not loaded\n");
    exit(2);
}

$golden = $argv[1] ?? __DIR__ . '/golden.tsv';
$wordsFile = $argv[2] ?? __DIR__ . '/words.txt';
$lines = @file($golden, FILE_IGNORE_NEW_LINES);
$wordLines = @file($wordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    fwrite(STDERR, "cannot read golden: $golden\n");
    exit(2);
}
if ($wordLines === false || $wordLines === []) {
    fwrite(STDERR, "cannot read non-empty word list: $wordsFile\n");
    exit(2);
}

$words = [];
foreach ($wordLines as $word) {
    if (str_contains($word, "\t")) {
        fwrite(STDERR, "word list contains a tab: $word\n");
        exit(2);
    }
    if (isset($words[$word])) {
        fwrite(STDERR, "duplicate word in word list: $word\n");
        exit(2);
    }
    $words[$word] = true;
}
$modes = array_fill_keys(PARITY_MODES, true);

/** Order-independent normalisation for the multi-token encoders. */
function norm_set(string $s): string
{
    $tokens = array_filter(explode('|', $s), static fn($token) => $token !== '');
    sort($tokens);
    return implode('|', $tokens);
}

/** This extension's output for one mode, normalised the same way as the golden. */
function phonetic_out(string $mode, string $word): string
{
    switch ($mode) {
        case 'dm':
            $result = double_metaphone($word);
            return $result['primary'] . '|' . $result['alternate'];
        case 'nysiis_strict':
            return nysiis($word);
        case 'nysiis_full':
            return nysiis($word, 0);
        case 'mra':
            return match_rating($word);
        case 'dmsoundex':
            return norm_set(implode('|', dm_soundex($word)));
        case 'bmpm_gen_approx':
            return norm_set(bmpm($word, BMPM_GENERIC, BMPM_APPROX));
        case 'bmpm_gen_exact':
            return norm_set(bmpm($word, BMPM_GENERIC, BMPM_EXACT));
        case 'bmpm_ash_approx':
            return norm_set(bmpm($word, BMPM_ASHKENAZI, BMPM_APPROX));
        case 'bmpm_ash_exact':
            return norm_set(bmpm($word, BMPM_ASHKENAZI, BMPM_EXACT));
        case 'bmpm_sep_approx':
            return norm_set(bmpm($word, BMPM_SEPHARDIC, BMPM_APPROX));
        case 'bmpm_sep_exact':
            return norm_set(bmpm($word, BMPM_SEPHARDIC, BMPM_EXACT));
    }
    throw new LogicException("unsupported parity mode: $mode");
}

$checked = 0;
$failures = 0;
$seen = [];
foreach ($lines as $lineNumber => $line) {
    if ($line === '') {
        continue;
    }
    $parts = explode("\t", $line, 3);
    if (count($parts) !== 3) {
        $displayLine = $lineNumber + 1;
        fwrite(STDERR, "malformed golden row at line $displayLine\n");
        exit(2);
    }
    [$mode, $word, $expected] = $parts;
    if (!isset($modes[$mode])) {
        fwrite(STDERR, "unknown mode in golden: $mode\n");
        exit(2);
    }
    if (!isset($words[$word])) {
        fwrite(STDERR, "unexpected word in golden [$mode]: $word\n");
        exit(2);
    }
    $pair = $mode . "\0" . $word;
    if (isset($seen[$pair])) {
        fwrite(STDERR, "duplicate golden pair [$mode]: $word\n");
        exit(2);
    }
    $seen[$pair] = true;

    $actual = phonetic_out($mode, $word);
    $checked++;
    if ($actual !== $expected) {
        $failures++;
        fwrite(STDERR, "MISMATCH [$mode] \"$word\": commons=$expected phonetic=$actual\n");
    }
}

$missing = [];
foreach (PARITY_MODES as $mode) {
    foreach ($words as $word => $_) {
        if (!isset($seen[$mode . "\0" . $word])) {
            $missing[] = "[$mode] $word";
        }
    }
}
if ($missing !== []) {
    $sample = implode(', ', array_slice($missing, 0, 3));
    fwrite(STDERR, sprintf("incomplete golden: %d missing pair(s); first: %s\n", count($missing), $sample));
    exit(2);
}

printf("parity: %d checked, %d mismatch(es)\n", $checked, $failures);
exit($failures === 0 ? 0 : 1);
