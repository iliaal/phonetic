--TEST--
Oracle parity checker rejects unknown modes in golden rows
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

$dir = sys_get_temp_dir() . '/phonetic-parity-unknown-mode-' . getmypid();
mkdir($dir);
$words = $dir . '/words.txt';
$golden = $dir . '/golden.tsv';
file_put_contents($words, "Smith\n");
file_put_contents($golden, "dmx\tSmith\tSM0,XMT\n");

[$status, $stdout, $stderr] = parity_check_run($golden, $words);

var_dump($status);
var_dump(str_contains($stderr, 'unknown mode in golden: dmx'));

unlink($golden);
unlink($words);
rmdir($dir);
?>
--EXPECT--
int(2)
bool(true)
