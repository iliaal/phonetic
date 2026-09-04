--TEST--
Oracle parity checker rejects tab and duplicate entries in the word list
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

$dir = sys_get_temp_dir() . '/phonetic-parity-wordlist-' . getmypid();
mkdir($dir);

$words = $dir . '/words-tab.txt';
$golden = $dir . '/golden-tab.tsv';
file_put_contents($words, "Smi\tth\n");
file_put_contents($golden, "dm\tSmith\tSM0,XMT\n");
[$tabStatus, $stdout, $tabStderr] = parity_check_run($golden, $words);
var_dump($tabStatus);
var_dump(str_contains($tabStderr, 'word list contains a tab'));

$words = $dir . '/words-dup.txt';
$golden = $dir . '/golden-dup.tsv';
file_put_contents($words, "Smith\nSmith\n");
file_put_contents($golden, "dm\tSmith\tSM0,XMT\n");
[$dupStatus, $stdout, $dupStderr] = parity_check_run($golden, $words);
var_dump($dupStatus);
var_dump(str_contains($dupStderr, 'duplicate word in word list: Smith'));

foreach (glob($dir . '/*') as $file) {
    unlink($file);
}
rmdir($dir);
?>
--EXPECT--
int(2)
bool(true)
int(2)
bool(true)
