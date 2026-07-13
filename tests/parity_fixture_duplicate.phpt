--TEST--
Oracle parity checker rejects duplicate mode and word pairs
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

$dir = sys_get_temp_dir() . '/phonetic-parity-duplicate-' . getmypid();
mkdir($dir);
$words = $dir . '/words.txt';
$golden = $dir . '/golden.tsv';
file_put_contents($words, "Smith\n");
file_put_contents($golden, "dm\tSmith\tSM0,XMT\ndm\tSmith\tSM0,XMT\n");

[$status, $stdout, $stderr] = parity_check_run($golden, $words);

var_dump($status);
var_dump(str_contains($stderr, 'duplicate golden pair [dm]: Smith'));

unlink($golden);
unlink($words);
rmdir($dir);
?>
--EXPECT--
int(2)
bool(true)
