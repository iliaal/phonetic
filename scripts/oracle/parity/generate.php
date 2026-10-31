<?php

const CODEC_VERSION = '1.17.1';
const CODEC_SHA256 = 'f9f6cb103f2ddc3c99a9d80ada2ae7bf0685111fd6bffccb72033d1da4e6ff23';

$oracleDir = dirname(__DIR__);
$jar = $oracleDir . '/commons-codec-' . CODEC_VERSION . '.jar';
$oracle = $oracleDir . '/Oracle.java';
$wordsFile = __DIR__ . '/words.txt';
$goldenFile = __DIR__ . '/golden.tsv';

if (!is_file($jar) || hash_file('sha256', $jar) !== CODEC_SHA256) {
    fwrite(STDERR, "missing or unverified oracle jar; run scripts/oracle/fetch-codec.sh\n");
    exit(2);
}

$words = file($wordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($words === false || $words === []) {
    fwrite(STDERR, "cannot read non-empty word list: $wordsFile\n");
    exit(2);
}
$seenWords = [];
foreach ($words as $word) {
    if (str_contains($word, "\t")) {
        fwrite(STDERR, "word list contains a tab: $word\n");
        exit(2);
    }
    if (isset($seenWords[$word])) {
        fwrite(STDERR, "duplicate word in word list: $word\n");
        exit(2);
    }
    $seenWords[$word] = true;
}
$input = implode("\n", $words) . "\n";

$modes = [
    'dm' => [['dm'], false],
    'nysiis_strict' => [['nysiis', 'strict'], false],
    'nysiis_full' => [['nysiis', 'full'], false],
    'mra' => [['mra'], false],
    'dmsoundex' => [['dmsoundex'], true],
    'bmpm_gen_approx' => [['bmpm', 'gen', 'approx'], true],
    'bmpm_gen_exact' => [['bmpm', 'gen', 'exact'], true],
    'bmpm_ash_approx' => [['bmpm', 'ash', 'approx'], true],
    'bmpm_ash_exact' => [['bmpm', 'ash', 'exact'], true],
    'bmpm_sep_approx' => [['bmpm', 'sep', 'approx'], true],
    'bmpm_sep_exact' => [['bmpm', 'sep', 'exact'], true],
    'bmpm_gen_forced' => [['bmpm', 'gen', 'approx', 'english'], true],
];

$rows = [];
foreach ($modes as $mode => [$arguments, $isSet]) {
    $command = array_merge(['java', '-cp', $jar, $oracle], $arguments);
    // Writing all stdin before draining stdout can deadlock once both pipes
    // fill. File-backed streams also let the oracle emit stderr at any time.
    $streams = [tmpfile(), tmpfile(), tmpfile()];
    try {
        if (in_array(false, $streams, true)
            || fwrite($streams[0], $input) !== strlen($input)
            || !rewind($streams[0])) {
            fwrite(STDERR, "could not prepare oracle streams for $mode\n");
            exit(2);
        }
        $process = proc_open($command, $streams, $pipes, $oracleDir);
        if (!is_resource($process)) {
            fwrite(STDERR, "could not start oracle for $mode\n");
            exit(2);
        }
        $exit = proc_close($process);
        rewind($streams[1]);
        rewind($streams[2]);
        $stdout = stream_get_contents($streams[1]);
        $stderr = stream_get_contents($streams[2]);
    } finally {
        foreach ($streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
    if ($exit !== 0) {
        fwrite(STDERR, "oracle failed for $mode: $stderr\n");
        exit(2);
    }

    // Remove only the terminal line separator. Tabs and spaces are TSV data,
    // and extra blank rows must remain visible to the row-count guard.
    $lines = preg_split('/\r\n|\n|\r/', $stdout);
    if (end($lines) === '') {
        array_pop($lines);
    }
    if (count($lines) !== count($words)) {
        fwrite(STDERR, "oracle returned the wrong row count for $mode\n");
        exit(2);
    }
    foreach ($lines as $index => $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2 || $parts[0] !== $words[$index]) {
            fwrite(STDERR, "oracle word/order mismatch for $mode at row " . ($index + 1) . "\n");
            exit(2);
        }
        $output = $parts[1];
        if ($isSet) {
            $tokens = array_filter(explode('|', $output), static fn($token) => $token !== '');
            sort($tokens);
            $output = implode('|', $tokens);
        }
        $rows[] = $mode . "\t" . $words[$index] . "\t" . $output;
    }
}

$temporary = $goldenFile . '.' . bin2hex(random_bytes(8)) . '.tmp';
$content = implode("\n", $rows) . "\n";
$stream = fopen($temporary, 'x');
if ($stream === false) {
    fwrite(STDERR, "could not create temporary output for $goldenFile\n");
    exit(2);
}
$written = fwrite($stream, $content);
$closed = fclose($stream);
if ($written !== strlen($content) || !$closed || !rename($temporary, $goldenFile)) {
    @unlink($temporary);
    fwrite(STDERR, "could not replace $goldenFile\n");
    exit(2);
}

printf("generated %d parity rows across %d modes\n", count($rows), count($modes));
