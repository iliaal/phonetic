--TEST--
dm_soundex_match(): unencodable input ("000000") never matches
--EXTENSIONS--
phonetic
--FILE--
<?php
// Both sides encode to the degenerate "000000" — that must NOT count as a match.
var_dump(dm_soundex_match("Иванов", "Петров"));
var_dump(dm_soundex_match("12345", "苏州"));
var_dump(dm_soundex_match(" ", "\t"));
// One unencodable side against a real name.
var_dump(dm_soundex_match("Katz", "Иванов"));
// Real names still match.
var_dump(dm_soundex_match("Katz", "Katz"));
// The encoder itself keeps oracle parity: unencodable input codes "000000".
var_dump(dm_soundex("Иванов"));
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
array(1) {
  [0]=>
  string(6) "000000"
}
