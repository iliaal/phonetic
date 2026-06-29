--TEST--
bmpm(): empty and whitespace-only input
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm(""));
var_dump(bmpm("   "));
var_dump(bmpm("-"));
?>
--EXPECT--
string(0) ""
string(0) ""
string(0) ""
