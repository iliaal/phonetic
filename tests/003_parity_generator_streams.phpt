--TEST--
Parity generator handles input, output, and diagnostics larger than pipe buffers
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/phonetic-streams-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/parity');
$jar = 'fixture jar';
file_put_contents($tmp . '/commons-codec-1.17.1.jar', $jar);
$source = file_get_contents($root . '/scripts/oracle/parity/generate.php');
$source = str_replace("['java', '-cp', \$jar, \$oracle]", '[PHP_BINARY, "-n", $oracle]', $source);
$source = preg_replace("/const CODEC_SHA256 = '[a-f0-9]+';/",
    "const CODEC_SHA256 = '" . hash('sha256', $jar) . "';", $source);
file_put_contents($tmp . '/parity/generate.php', $source);
// Unique names total over 1 MiB, exceeding ordinary Linux and Windows pipes.
$words = [];
for ($i = 0; $i < 1024; $i++) {
    $words[] = str_repeat('a', 1024) . $i;
}
file_put_contents($tmp . '/parity/words.txt', implode("\n", $words) . "\n");
$oracle = <<<'PHP'
<?php
$scenario = file_get_contents(__DIR__ . '/scenario');
if ($scenario !== 'stdout') {
    fwrite(STDERR, str_repeat('diagnostic ', 100000) . "diagnostic end\n");
}
if ($scenario === 'failure') {
    exit(7);
}
while (($line = fgets(STDIN)) !== false) {
    echo rtrim($line, "\r\n"), "\tX\n";
}
PHP;
file_put_contents($tmp . '/Oracle.java', $oracle);
try {
    foreach (['stdout', 'stderr', 'failure'] as $scenario) {
        file_put_contents($tmp . '/scenario', $scenario);
        file_put_contents($tmp . '/parity/golden.tsv', 'unchanged');
        // The test itself must not inherit the pipe deadlock it is checking.
        $out = tmpfile();
        $err = tmpfile();
        $process = proc_open([PHP_BINARY, '-n', $tmp . '/parity/generate.php'],
            [1 => $out, 2 => $err], $pipes);
        $deadline = microtime(true) + 30;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        if ($status['running']) {
            proc_terminate($process);
            proc_close($process);
            fclose($out);
            fclose($err);
            echo "$scenario: generator timed out\n";
            break;
        }
        $exit = $status['exitcode'];
        proc_close($process);
        rewind($err);
        $stderr = stream_get_contents($err);
        fclose($out);
        fclose($err);
        $golden = file_get_contents($tmp . '/parity/golden.tsv');
        if ($scenario === 'failure') {
            if ($exit !== 2 || $golden !== 'unchanged'
                || !str_contains($stderr, 'oracle failed for dm: diagnostic ')
                || !str_contains($stderr, 'diagnostic end')) {
                echo "failure: exit status, diagnostics, or original golden lost\n";
            }
        } else {
            // Derive the expected modes from the production source of truth.
            preg_match_all("/^    '([^']+)' => \\[\\[/m", $source, $matches);
            $modes = $matches[1];
            $expected = '';
            foreach ($modes as $mode) {
                foreach ($words as $word) {
                    $expected .= "$mode\t$word\tX\n";
                }
            }
            if ($exit !== 0 || $golden !== $expected || count($modes) < 1) {
                echo "$scenario: generated rows corrupted (exit $exit)\n";
            }
        }
    }
} finally {
    foreach (glob($tmp . '/parity/*') as $file) {
        unlink($file);
    }
    rmdir($tmp . '/parity');
    foreach (glob($tmp . '/*') as $file) {
        unlink($file);
    }
    rmdir($tmp);
}
echo "ok\n";
?>
--EXPECT--
ok
