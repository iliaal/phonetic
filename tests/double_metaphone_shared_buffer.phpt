--TEST--
Double Metaphone reuses the folded input buffer for encoding and matching
--EXTENSIONS--
phonetic
--SKIPIF--
<?php if (!function_exists('memory_reset_peak_usage')) die('skip requires memory_reset_peak_usage (PHP 8.2+)'); ?>
--INI--
memory_limit=64M
--FILE--
<?php
function checkMemory(callable $encode, int $inputSize): void {
    memory_reset_peak_usage();
    $before = memory_get_usage();
    $result = $encode();
    $extra = memory_get_peak_usage() - $before;
    var_dump($result);
    // Allow allocator rounding and result storage, but not a second full copy.
    var_dump($extra < $inputSize * 1.5);
}
$input = str_repeat('BC', 512 * 1024);
checkMemory(fn() => double_metaphone($input), strlen($input));
checkMemory(fn() => double_metaphone_match($input, 'BCBC'), strlen($input));
checkMemory(fn() => double_metaphone_match('BCBC', $input), strlen($input));
unset($input);
// Two-byte UTF-8 letters expand to two ASCII bytes, preserving the memory bound.
$input = str_repeat("Æ", 512 * 1024);
checkMemory(fn() => double_metaphone($input), strlen($input));
checkMemory(fn() => double_metaphone_match($input, 'A'), strlen($input));
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
bool(true)
int(2)
bool(true)
int(2)
bool(true)
array(2) {
  ["primary"]=>
  string(1) "A"
  ["alternate"]=>
  string(1) "A"
}
bool(true)
int(2)
bool(true)
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
