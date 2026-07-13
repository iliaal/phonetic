--TEST--
Oracle parity checker rejects words outside the canonical corpus
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

$dir = sys_get_temp_dir() . '/phonetic-parity-unexpected-' . getmypid();
mkdir($dir);
$words = $dir . '/words.txt';
$golden = $dir . '/golden.tsv';
file_put_contents($words, "Smith\n");
file_put_contents($golden, "dm\tJones\tJNS,ANS\n");

[$status, $stdout, $stderr] = parity_check_run($golden, $words);

var_dump($status);
var_dump(str_contains($stderr, 'unexpected word in golden [dm]: Jones'));

unlink($golden);
unlink($words);
rmdir($dir);
?>
--EXPECT--
int(2)
bool(true)
