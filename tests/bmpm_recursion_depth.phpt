--TEST--
bmpm(): deep d'/prefix recursion is depth-bounded (no native stack overflow)
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Before the BMPM_MAX_PREFIX_DEPTH cap, "d" followed by thousands of
 * apostrophes drove O(input-length) native C recursion and crashed the
 * process. These must now return a string and survive. */
$deep = "d" . str_repeat("'", 1000);
var_dump(is_string(bmpm($deep)));

$deep_prefix = str_repeat("de ", 1000) . "cohen";
var_dump(is_string(bmpm($deep_prefix)));

/* Ordinary input is unaffected by the cap. */
var_dump(bmpm("Jackson") !== "");
echo "survived\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
survived
