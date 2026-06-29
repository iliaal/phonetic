--TEST--
phonetic smoke: module loads and version matches
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(extension_loaded('phonetic'));
var_dump(phpversion('phonetic') === '0.1.0');
?>
--EXPECT--
bool(true)
bool(true)
