--TEST--
dm_soundex(): input length is capped to bound CPU on untrusted input
--EXTENSIONS--
phonetic
--FILE--
<?php
// At or below the cap (4096 bytes) is fine.
var_dump(is_array(dm_soundex(str_repeat("a", 4096))));

// Above the cap throws ValueError on the offending argument.
try {
    dm_soundex(str_repeat("a", 4097));
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
try {
    dm_soundex_match("Moskowitz", str_repeat("a", 4097));
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
// A normal name pair is unaffected.
var_dump(dm_soundex_match("Moskowitz", "Moskovitz"));
?>
--EXPECT--
bool(true)
dm_soundex(): Argument #1 ($string) must not exceed 4096 bytes
dm_soundex_match(): Argument #2 ($string2) must not exceed 4096 bytes
bool(true)
