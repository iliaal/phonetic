--TEST--
double_metaphone(): non-letter bytes are skipped, not duplicated
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Digits/punctuation must emit nothing and advance one (Commons Codec
 * `default: index++`), not re-emit the previous phoneme. */
var_dump(double_metaphone("a1b2"));
var_dump(double_metaphone("AB!"));
var_dump(double_metaphone("abc123"));

/* A separator (hyphen, space, anything non-letter) is skipped, so these
 * three spellings collapse to the same code. */
$h = double_metaphone("Smith-Jones", 0);
$s = double_metaphone("Smith Jones", 0);
$j = double_metaphone("SmithJones", 0);
var_dump($h === $s, $s === $j);
echo $h["primary"], "\n";
?>
--EXPECT--
array(2) {
  ["primary"]=>
  string(2) "AP"
  ["alternate"]=>
  string(2) "AP"
}
array(2) {
  ["primary"]=>
  string(2) "AP"
  ["alternate"]=>
  string(2) "AP"
}
array(2) {
  ["primary"]=>
  string(3) "APK"
  ["alternate"]=>
  string(3) "APK"
}
bool(true)
bool(true)
SM0JNS
