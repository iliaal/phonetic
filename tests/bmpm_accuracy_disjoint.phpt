--TEST--
bmpm(): a misplaced accuracy constant is rejected, not silently a name type
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach ([BMPM_APPROX, BMPM_EXACT] as $c) {
    try {
        bmpm("Jackson", $c);
    } catch (\ValueError $e) {
        echo $e->getMessage(), "\n";
    }
}

var_dump(bmpm("Jackson", BMPM_GENERIC, BMPM_APPROX)
         !== bmpm("Jackson", BMPM_GENERIC, BMPM_EXACT));

var_dump(bmpm("Jackson", BMPM_GENERIC) === bmpm("Jackson", BMPM_GENERIC, BMPM_APPROX));
?>
--EXPECT--
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bool(true)
bool(true)
