--TEST--
malformed UTF-8 decodes as single raw bytes (Latin-1 fallback) in all engines
--EXTENSIONS--
phonetic
--FILE--
<?php
// A stray lead byte must consume only itself: the A and B after \xE0 survive.
var_dump(double_metaphone("\xE0AB"));
// Truncated trail at end of string.
var_dump(double_metaphone("ab\xC3"));
var_dump(nysiis("a\xC3"));
// Bare continuation byte (\x80 is a Latin-1 control, stripped as non-letter).
var_dump(match_rating("x\x80y"));
// Truncated 3-byte sequence.
var_dump(dm_soundex("a\xE0\xA0"));
var_dump(bmpm("\xFF\xFE"));
// Overlong and non-scalar forms are malformed: the lead byte folds byte-wise
// (0xC0/0xE0 -> A, 0xED -> I, 0xF4 -> O; all initial vowels map to A) instead of
// being accepted as the encoded code point. Before the fix these decoded the
// overlong/surrogate value and dropped it, yielding P|P.
var_dump(double_metaphone("\xC0\xAFB"));      // overlong 2-byte
var_dump(double_metaphone("\xE0\x80\x80B"));  // overlong 3-byte
var_dump(double_metaphone("\xED\xA0\x80B"));  // surrogate U+D800
var_dump(double_metaphone("\xF4\x90\x80\x80B")); // > U+10FFFF
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
string(1) "A"
string(2) "XY"
array(1) {
  [0]=>
  string(6) "000000"
}
string(0) ""
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
