--TEST--
dm_soundex_match(): true when the Daitch-Mokotoff code sets intersect
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(dm_soundex_match("Moskowitz", "Moskovitz"));
var_dump(dm_soundex_match("Peterson", "Peters"));
var_dump(dm_soundex_match("Smith", "Xylophone"));
var_dump(dm_soundex_match("", ""));
var_dump(dm_soundex_match("Katz", "Katz"));
// Multi-code: first codes differ (097400 vs 097500) but intersect on 097500.
var_dump(dm_soundex("Auerbach"));
var_dump(dm_soundex("Oerback"));
var_dump(dm_soundex_match("Auerbach", "Oerback"));
// Dual "000000": pure vowels coded vs unencodable junk encode equal, match not.
var_dump(dm_soundex("A") === dm_soundex("1"));
var_dump(dm_soundex_match("A", "1"));
var_dump(dm_soundex_match("A", "E"));
// Identical unencodable operands: must stay false (not identity short-circuit).
var_dump(dm_soundex_match("Иванов", "Иванов"));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
array(2) {
  [0]=>
  string(6) "097400"
  [1]=>
  string(6) "097500"
}
array(2) {
  [0]=>
  string(6) "097500"
  [1]=>
  string(6) "097450"
}
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
