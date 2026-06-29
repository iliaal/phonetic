--TEST--
dm_soundex(): adjacent identical codes collapse (skip-duplicate rule)
--EXTENSIONS--
phonetic
--FILE--
<?php
// "ss" both code 4 but the second is dropped; likewise the trailing "nn"
// codes 6 only once -> 294660 rather than 2944660 / 2946660.
var_dump(dm_soundex("Strassmann"));
var_dump(dm_soundex("Grossman"));
// Doubled vowel at the start codes a single leading 0.
var_dump(dm_soundex("Aaron"));
?>
--EXPECT--
array(1) {
  [0]=>
  string(6) "294660"
}
array(1) {
  [0]=>
  string(6) "594660"
}
array(1) {
  [0]=>
  string(6) "096000"
}
