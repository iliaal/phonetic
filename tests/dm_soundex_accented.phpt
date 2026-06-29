--TEST--
dm_soundex(): accent folding and Unicode rule patterns
--EXTENSIONS--
phonetic
--FILE--
<?php
// u-diaeresis folds to u; identical to ASCII "Mueller".
var_dump(dm_soundex("Müller") === dm_soundex("Mueller"));
// Polish: l-stroke folds to l, e-ogonek branches (default code "|6").
var_dump(dm_soundex("Wałęsa"));
// Romanian s-comma is dropped (no rule, no folding); same as "Ceauescu".
var_dump(dm_soundex("Ceauşescu"));
?>
--EXPECT--
bool(true)
array(2) {
  [0]=>
  string(6) "784000"
  [1]=>
  string(6) "786400"
}
array(2) {
  [0]=>
  string(6) "440000"
  [1]=>
  string(6) "540000"
}
