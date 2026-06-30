--TEST--
bmpm_match(): true when the Beider-Morse phoneme sets intersect
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm_match("Jackson", "Jaxon"));
var_dump(bmpm_match("Mueller", "Miller"));
var_dump(bmpm_match("Smith", "Xylophone"));
var_dump(bmpm_match("", ""));
// accuracy / name_type / language are forwarded like bmpm()
var_dump(bmpm_match("Moskowitz", "Moskovitz", BMPM_ASHKENAZI));
var_dump(bmpm_match("Peterson", "Peterson", BMPM_GENERIC, BMPM_EXACT, "english"));
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
