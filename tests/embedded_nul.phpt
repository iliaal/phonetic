--TEST--
embedded NUL bytes are handled binary-safely by all engines
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(double_metaphone("a\0b"));
var_dump(nysiis("a\0b"));
// MRA keeps the NUL (reference cleanName only strips punct/space); hex so
// the expected output stays printable.
var_dump(bin2hex(match_rating("a\0b")));
var_dump(dm_soundex("a\0b"));
var_dump(bmpm("a\0b"));
var_dump(nysiis_match("a\0b", "ab"));
?>
--EXPECT--
array(2) {
  ["primary"]=>
  string(2) "AP"
  ["alternate"]=>
  string(2) "AP"
}
string(2) "AB"
string(6) "410042"
array(1) {
  [0]=>
  string(6) "070000"
}
string(14) "Yp|af|ap|of|op"
bool(true)
