--TEST--
phonetic smoke: module loads and version is set
--EXTENSIONS--
phonetic
--FILE--
<?php
// Version string is owned by PHP_PHONETIC_VERSION in php_phonetic.h only.
// Assert shape here so a bump does not require a second hard-coded edit.
var_dump(extension_loaded('phonetic'));
$v = phpversion('phonetic');
var_dump(is_string($v) && (bool) preg_match('/^\d+\.\d+\.\d+$/', $v));
?>
--EXPECT--
bool(true)
bool(true)
