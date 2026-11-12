--TEST--
Parity checker bounds missing-pair diagnostics while preserving counts and sample order
--EXTENSIONS--
phonetic
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
require __DIR__ . '/parity_checker.inc';

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/phonetic-parity-missing-' . bin2hex(random_bytes(8));
mkdir($dir);
$golden = $dir . '/golden.tsv';
$words = $dir . '/words.txt';
try {
    // Exercise below, at, and above the three-example diagnostic limit.
    $rows = file($root . '/scripts/oracle/parity/golden.tsv', FILE_IGNORE_NEW_LINES);
    foreach ([1, 2, 3, 4] as $count) {
        $removed = array_slice($rows, -$count);
        file_put_contents($golden, implode("\n", array_slice($rows, 0, -$count)) . "\n");
        $sample = [];
        foreach (array_slice($removed, 0, 3) as $row) {
            [$mode, $word] = explode("\t", $row, 3);
            $sample[] = "[$mode] $word";
        }
        [$status, $stdout, $stderr] = parity_check_run(
            $golden, $root . '/scripts/oracle/parity/words.txt');
        $expected = "incomplete golden: $count missing pair(s); first: " . implode(', ', $sample) . "\n";
        if ($status !== 2 || $stdout !== '' || $stderr !== $expected) {
            echo "sample $count mismatch: $status\n$stdout$stderr";
        }
    }

    // No encodes are needed for an empty fixture, only the completeness scan.
    // The old full diagnostic list exhausted 64 MiB with the Zend allocator.
    $stream = fopen($words, 'w');
    for ($i = 0; $i < 100000; $i++) {
        fprintf($stream, "Name%06d\n", $i);
    }
    fclose($stream);
    file_put_contents($golden, '');
    [$status, $stdout, $stderr] = parity_check_run($golden, $words, ['-d', 'memory_limit=64M']);
    $expected = "incomplete golden: 1200000 missing pair(s); first: [dm] Name000000, [dm] Name000001, [dm] Name000002\n";
    if ($status !== 2 || $stdout !== '' || $stderr !== $expected) {
        echo "large corpus mismatch: $status\n$stdout$stderr";
    }
} finally {
    unlink($golden);
    unlink($words);
    rmdir($dir);
}
echo "ok\n";
?>
--EXPECT--
ok
