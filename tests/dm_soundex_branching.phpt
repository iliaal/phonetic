--TEST--
dm_soundex(): names that branch into multiple distinct codes
--EXTENSIONS--
phonetic
--FILE--
<?php
// Auerbach is the example from the Commons Codec Javadoc.
var_dump(dm_soundex("Auerbach"));
var_dump(dm_soundex("Peters"));
var_dump(dm_soundex("Schwarz"));
var_dump(dm_soundex("Tattenbach"));
?>
--EXPECT--
array(2) {
  [0]=>
  string(6) "097400"
  [1]=>
  string(6) "097500"
}
array(2) {
  [0]=>
  string(6) "734000"
  [1]=>
  string(6) "739400"
}
array(2) {
  [0]=>
  string(6) "474000"
  [1]=>
  string(6) "479400"
}
array(2) {
  [0]=>
  string(6) "336740"
  [1]=>
  string(6) "336750"
}
