--TEST--
Parity generator rejects per-input oracle errors before replacing golden data
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$tmp = sys_get_temp_dir() . '/phonetic-oracle-errors-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/parity');
$jar = 'fixture jar';
file_put_contents($tmp . '/commons-codec-1.17.1.jar', $jar);
$source = file_get_contents(dirname(__DIR__) . '/scripts/oracle/parity/generate.php');
// Derive every mode and its subprocess arguments from the production matrix.
preg_match_all("/^    '([^']+)' => \\[\\[([^\\]]+)\\], (?:true|false)\\],$/m", $source, $matches, PREG_SET_ORDER);
if ($matches === []) {
    throw new RuntimeException('could not extract generator modes');
}
$source = str_replace("['java', '-cp', \$jar, \$oracle]", '[PHP_BINARY, "-n", $oracle]', $source);
$source = preg_replace("/const CODEC_SHA256 = '[a-f0-9]+';/",
    "const CODEC_SHA256 = '" . hash('sha256', $jar) . "';", $source);
file_put_contents($tmp . '/parity/generate.php', $source);
file_put_contents($tmp . '/Oracle.java', <<<'PHP'
<?php
$case = json_decode(file_get_contents(__DIR__ . '/case.json'), true);
$target = array_slice($argv, 1) === $case['arguments'];
$index = 0;
while (($word = fgets(STDIN)) !== false) {
    $output = $target && $index++ === $case['row'] ? $case['output'] : 'CODE';
    echo rtrim($word, "\r\n"), "\t", $output, "\n";
}
PHP);
try {
    foreach ($matches as $mode) {
        preg_match_all("/'([^']+)'/", $mode[2], $arguments);
        foreach ([
            ['first', "Smith\nJones\n", 0, '!ERROR:'],
            // Sorting this as a set moves the error marker away from the start.
            ['adjacent', "Smith\nJones\n", 1, '!ERROR:fixture failure|!!detail'],
            ['blank', "Smith\n\nJones\n", 1, '!ERROR:fixture failure'],
        ] as [$label, $words, $row, $output]) {
            file_put_contents($tmp . '/case.json', json_encode([
                'arguments' => $arguments[1], 'row' => $row, 'output' => $output,
            ]));
            file_put_contents($tmp . '/parity/words.txt', $words);
            file_put_contents($tmp . '/parity/golden.tsv', 'unchanged');
            $process = proc_open([PHP_BINARY, '-n', $tmp . '/parity/generate.php'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $expected = 'oracle encoding failed for ' . $mode[1] . ' at row ' . ($row + 1) . ': ' . $output . "\n";
            if ($status !== 2 || $stdout !== '' || $stderr !== $expected
                || file_get_contents($tmp . '/parity/golden.tsv') !== 'unchanged'
                || glob($tmp . '/parity/golden.tsv.*.tmp') !== []) {
                echo "$mode[1] $label: expected encoding-error rejection with original golden intact\n$stdout$stderr";
            }
        }
    }
} finally {
    foreach (glob($tmp . '/parity/*') as $file) unlink($file);
    rmdir($tmp . '/parity');
    foreach (glob($tmp . '/*') as $file) unlink($file);
    rmdir($tmp);
}
echo "ok\n";
?>
--EXPECT--
ok
