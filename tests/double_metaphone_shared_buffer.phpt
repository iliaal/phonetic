--TEST--
Double Metaphone reuses the folded input buffer for encoding and matching
--EXTENSIONS--
phonetic
--FILE--
<?php
// The allocation bound is verified on Linux; allocator peaks differ by platform.
// Keep the same large-input correctness checks on every platform.
if (PHP_OS_FAMILY === 'Linux') {
    ini_set('memory_limit', (string) (memory_get_usage(true) + 10 * 1024 * 1024));
}
// On Linux, input plus one folded buffer fit; the old second copy does not.
$input = str_repeat('BC', 3 * 512 * 1024);
var_dump(double_metaphone($input));
var_dump(double_metaphone_match($input, 'BCBC'));
var_dump(double_metaphone_match('BCBC', $input));
unset($input);
// Also exercise two-byte UTF-8 letters that fold to two ASCII bytes.
$input = str_repeat("Æ", 1024 * 1024);
var_dump(double_metaphone($input));
var_dump(double_metaphone_match($input, 'A'));
unset($input);
foreach (['', "中文", "\0\t\n", '--'] as $input) {
    var_dump(double_metaphone($input));
}
?>
--EXPECT--
array(2) {
  ["primary"]=>
  string(4) "PKPK"
  ["alternate"]=>
  string(4) "PKPK"
}
int(2)
int(2)
array(2) {
  ["primary"]=>
  string(1) "A"
  ["alternate"]=>
  string(1) "A"
}
int(2)
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(0) ""
}
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(0) ""
}
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(0) ""
}
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(0) ""
}
