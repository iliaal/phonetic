--TEST--
dm_soundex(): input length is capped to bound CPU on untrusted input
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(dm_soundex(str_repeat("a", 4096)));
var_dump(dm_soundex(str_repeat("a", 4096)) === dm_soundex("a"));

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
var_dump(dm_soundex_match("Moskowitz", "Moskovitz"));
?>
--EXPECT--
array(1) {
  [0]=>
  string(6) "000000"
}
bool(true)
dm_soundex(): Argument #1 ($string) must not exceed 4096 bytes
dm_soundex_match(): Argument #2 ($b) must not exceed 4096 bytes
bool(true)
