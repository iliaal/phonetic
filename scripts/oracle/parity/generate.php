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
];

$rows = [];
foreach ($modes as $mode => [$arguments, $isSet]) {
    $command = array_merge(['java', '-cp', $jar, $oracle], $arguments);
    $pipes = [];
    $process = proc_open($command, [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w'],
    ], $pipes, $oracleDir);
    if (!is_resource($process)) {
        fwrite(STDERR, "could not start oracle for $mode\n");
        exit(2);
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        fwrite(STDERR, "oracle failed for $mode: $stderr\n");
        exit(2);
    }

    $lines = preg_split('/\R/', trim($stdout));
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

$temporary = $goldenFile . '.tmp';
$content = implode("\n", $rows) . "\n";
if (file_put_contents($temporary, $content) === false || !rename($temporary, $goldenFile)) {
    @unlink($temporary);
    fwrite(STDERR, "could not replace $goldenFile\n");
    exit(2);
}

printf("generated %d parity rows across %d modes\n", count($rows), count($modes));
