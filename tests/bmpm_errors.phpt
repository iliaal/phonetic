--TEST--
bmpm(): invalid name type, accuracy and language are rejected
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach ([
    fn() => bmpm("Smith", 9),
    fn() => bmpm("Smith", BMPM_GENERIC, 0),
    fn() => bmpm("Smith", BMPM_GENERIC, BMPM_APPROX, "klingon"),
    fn() => bmpm("Smith", BMPM_ASHKENAZI, BMPM_APPROX, "italian"),
] as $fn) {
    try {
        $fn();
    } catch (\ValueError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #3 ($accuracy) must be either BMPM_APPROX or BMPM_EXACT
bmpm(): Argument #4 ($language) "klingon" is not a known language for the given name type
bmpm(): Argument #4 ($language) "italian" is not a known language for the given name type
