--TEST--
Turkish dotted capital I (U+0130) lowercases to plain i
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm("İstanbul") === bmpm("istanbul"));
var_dump(dm_soundex("İzmir") === dm_soundex("Izmir"));
var_dump(dm_soundex("İzmir"));
?>
--EXPECT--
bool(true)
bool(true)
array(1) {
  [0]=>
  string(6) "046900"
}
