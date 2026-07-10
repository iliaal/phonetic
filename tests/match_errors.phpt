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
// $language is a raw PHP string: the echoed preview is binary-safe (embedded
// NUL and other non-printables render as '?') and bounded to 32 bytes, so an
// over-long invalid language cannot blow the exception message up to its size.
try {
    bmpm_match("a", "b", BMPM_GENERIC, BMPM_APPROX, "bad\0lang");
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
try {
    bmpm_match("a", "b", BMPM_GENERIC, BMPM_APPROX, str_repeat("Z", 100000));
} catch (\ValueError $e) {
    printf("long-language message length: %d\n", strlen($e->getMessage()));
}
?>
--EXPECTF--
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #4 ($accuracy) must be either BMPM_APPROX or BMPM_EXACT
bmpm_match(): Argument #5 ($language) "klingon" is not a known language for the given name type
bmpm_match(): Argument #5 ($language) "bad?lang" is not a known language for the given name type
long-language message length: 120
