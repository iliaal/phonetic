--TEST--
dm_soundex_match(): argument #1 honours the input cap; at-cap input succeeds
--EXTENSIONS--
phonetic
--FILE--
<?php
// dm_soundex_input_cap.phpt covers the encoder and match argument #2;
// this covers argument #1 and success exactly at the cap.
try {
    dm_soundex_match(str_repeat("a", 4097), "Moskowitz");
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
var_dump(dm_soundex_match(str_repeat("a", 4096), "a"));
?>
--EXPECT--
dm_soundex_match(): Argument #1 ($a) must not exceed 4096 bytes
bool(true)
