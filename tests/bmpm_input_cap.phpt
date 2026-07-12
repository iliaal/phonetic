--TEST--
bmpm(): input length is capped to bound CPU on untrusted input
--EXTENSIONS--
phonetic
--FILE--
<?php
// At or below the cap (4096 bytes) is fine; pin the exact encoding so a silent
// truncation or off-by-one at the boundary is caught, not just "a string".
var_dump(bmpm(str_repeat("a", 4096)));

foreach ([
    fn() => bmpm(str_repeat("a", 4097)),
    fn() => bmpm_match(str_repeat("a", 4097), "a"),
    fn() => bmpm_match("a", str_repeat("a", 4097)),
] as $fn) {
    try {
        $fn();
    } catch (\ValueError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(7) "D|a|i|o"
bmpm(): Argument #1 ($string) must not exceed 4096 bytes
bmpm_match(): Argument #1 ($a) must not exceed 4096 bytes
bmpm_match(): Argument #2 ($b) must not exceed 4096 bytes
