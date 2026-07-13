--TEST--
parity checker rejects a fixture missing configured mode/word pairs
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

$golden = 'data://text/plain,dm%09Smith%09SM0%7CXMT%0A';
$words = 'data://text/plain,Smith%0A';
[$exit, $stdout, $stderr] = parity_check_run($golden, $words);

var_dump($exit);
echo $stdout;
echo $stderr;
?>
--EXPECT--
int(2)
incomplete golden: 10 missing pair(s); first: [nysiis_strict] Smith, [nysiis_full] Smith, [mra] Smith
