--TEST--
Parity generator rejects invalid word lists before running the oracle or replacing golden data
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$tmp = sys_get_temp_dir() . '/phonetic-wordlist-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/parity');
$jar = 'fixture jar';
file_put_contents($tmp . '/commons-codec-1.17.1.jar', $jar);
$source = file_get_contents(dirname(__DIR__) . '/scripts/oracle/parity/generate.php');
$source = str_replace("['java', '-cp', \$jar, \$oracle]", '[PHP_BINARY, $oracle]', $source);
$source = preg_replace("/const CODEC_SHA256 = '[a-f0-9]+';/",
    "const CODEC_SHA256 = '" . hash('sha256', $jar) . "';", $source);
// Derive the expected modes from the production source of truth.
preg_match_all("/^    '([^']+)' => \\[\\[/m", $source, $matches);
$modes = $matches[1];
file_put_contents($tmp . '/parity/generate.php', $source);
file_put_contents($tmp . '/Oracle.java', <<<'PHP'
<?php
file_put_contents(__DIR__ . '/called', 'yes');
while (($line = fgets(STDIN)) !== false) {
    echo rtrim($line, "\r\n"), "\tCODE\n";
}
PHP);
try {
    foreach ([
        ['duplicate adjacent', "Smith\nSmith\n", false],
        ['duplicate separated', "Smith\nJones\nSmith\n", false],
        ['duplicate blank', "Smith\n\nSmith\n", false],
        ['tab adjacent', "Smith\nJo\tnes\n", false],
        ['tab blank', "Smith\n\nJo\tnes\n", false],
        ['valid unique', "Smith\nJones\n", true],
        ['valid blank', "Smith\n\nJones\n", true],
        ['valid numeric', "0\n1\n", true],
    ] as [$label, $words, $valid]) {
        @unlink($tmp . '/called');
        file_put_contents($tmp . '/parity/words.txt', $words);
        file_put_contents($tmp . '/parity/golden.tsv', 'unchanged');
        $process = proc_open([PHP_BINARY, '-n', $tmp . '/parity/generate.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $golden = file_get_contents($tmp . '/parity/golden.tsv');
        if ($valid) {
            $expectedRows = [];
            foreach ($modes as $mode) {
                foreach (file($tmp . '/parity/words.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $word) {
                    $expectedRows[] = "$mode\t$word\tCODE\n";
                }
            }
            if ($status !== 0 || !is_file($tmp . '/called') || $modes === [] || $golden !== implode('', $expectedRows)) {
                echo "$label: valid input failed\n$stdout$stderr";
            }
        } elseif ($status !== 2 || is_file($tmp . '/called') || $golden !== 'unchanged') {
            echo "$label: expected preflight rejection with original golden intact\n$stdout$stderr";
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
