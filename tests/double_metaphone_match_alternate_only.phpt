--TEST--
double_metaphone_match(): codes with an empty primary still cross on the alternate
--EXTENSIONS--
phonetic
--FILE--
<?php
/* A non-word-initial vowel followed by a word-final W emits nothing on the
 * primary and "F" on the alternate. Such a code is not "unencodable": it can
 * still cross at strength 1, and it must do so regardless of argument order. */
var_dump(double_metaphone("-EW"));
var_dump(double_metaphone("-AW"));
var_dump(double_metaphone("WA"));

// alternate-only vs alternate-only
var_dump(double_metaphone_match("-EW", "-AW"));
var_dump(double_metaphone_match("-AW", "-EW"));

// alternate-only vs a code that has both; symmetric in both directions
var_dump(double_metaphone_match("-EW", "WA"));
var_dump(double_metaphone_match("WA", "-EW"));

// identical alternate-only operands are a strength-1 self match, not 2
var_dump(double_metaphone_match("-EW", "-EW"));

// a genuinely unencodable side still never matches
var_dump(double_metaphone_match("-EW", " "));
var_dump(double_metaphone_match(" ", "-EW"));
?>
--EXPECT--
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(1) "F"
}
array(2) {
  ["primary"]=>
  string(0) ""
  ["alternate"]=>
  string(1) "F"
}
array(2) {
  ["primary"]=>
  string(1) "A"
  ["alternate"]=>
  string(1) "F"
}
int(1)
int(1)
int(1)
int(1)
int(1)
int(0)
int(0)
