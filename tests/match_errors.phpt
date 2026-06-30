--TEST--
bmpm_match(): argument validation mirrors bmpm()
--EXTENSIONS--
phonetic
--FILE--
<?php
try {
    bmpm_match("a", "b", 99);
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
try {
    bmpm_match("a", "b", BMPM_GENERIC, 99);
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
try {
    bmpm_match("a", "b", BMPM_GENERIC, BMPM_APPROX, "klingon");
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #4 ($accuracy) must be either BMPM_APPROX or BMPM_EXACT
bmpm_match(): Argument #5 ($language) "klingon" is not a known language for the given name type
