--TEST--
dm_soundex(): empty input yields an empty list; degenerate input still codes
--EXTENSIONS--
phonetic
--FILE--
<?php
// Empty input is the one API-level departure from the oracle: [] not ["000000"].
var_dump(dm_soundex(""));
// Non-empty input that codes nothing still returns "000000" (oracle parity).
var_dump(dm_soundex("   "));
var_dump(dm_soundex("-"));
// Digits are skipped but the name is still encoded.
var_dump(dm_soundex("123abc"));
?>
--EXPECT--
array(0) {
}
array(1) {
  [0]=>
  string(6) "000000"
}
array(1) {
  [0]=>
  string(6) "000000"
}
array(2) {
  [0]=>
  string(6) "074000"
  [1]=>
  string(6) "075000"
}
