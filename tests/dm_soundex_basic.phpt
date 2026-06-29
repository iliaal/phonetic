--TEST--
dm_soundex(): single-code names (Commons Codec 1.17.1 golden vectors)
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(dm_soundex("Moskowitz"));
var_dump(dm_soundex("Mueller"));
var_dump(dm_soundex("Strassmann"));
var_dump(dm_soundex("Katz"));
?>
--EXPECT--
array(1) {
  [0]=>
  string(6) "645740"
}
array(1) {
  [0]=>
  string(6) "689000"
}
array(1) {
  [0]=>
  string(6) "294660"
}
array(1) {
  [0]=>
  string(6) "540000"
}
