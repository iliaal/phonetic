--TEST--
bmpm_match(): handles disjoint prefix-amplified inputs at the public input cap
--EXTENSIONS--
phonetic
--FILE--
<?php
$a = str_repeat("de ", 1363) . "cohen";
$b = str_repeat("de ", 1363) . "xavier";

var_dump(strlen($a));
var_dump(strlen($b));
var_dump(bmpm_match($a, $b));
?>
--EXPECT--
int(4094)
int(4095)
bool(false)
