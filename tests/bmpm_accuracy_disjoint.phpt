--TEST--
bmpm(): a misplaced accuracy constant is rejected, not silently a name type
--EXTENSIONS--
phonetic
--FILE--
<?php
// BMPM_APPROX / BMPM_EXACT are disjoint from the name-type values, so passing an
// accuracy constant in the name_type position is caught by validation instead of
// silently running as BMPM_ASHKENAZI / BMPM_SEPHARDIC (the old value collision).
foreach ([BMPM_APPROX, BMPM_EXACT] as $c) {
    try {
        bmpm("Jackson", $c);
    } catch (\ValueError $e) {
        echo $e->getMessage(), "\n";
    }
}

// The constants still select the intended accuracy in the accuracy position.
var_dump(bmpm("Jackson", BMPM_GENERIC, BMPM_APPROX)
         !== bmpm("Jackson", BMPM_GENERIC, BMPM_EXACT));

// Default accuracy (omitted) equals an explicit BMPM_APPROX.
var_dump(bmpm("Jackson", BMPM_GENERIC) === bmpm("Jackson", BMPM_GENERIC, BMPM_APPROX));
?>
--EXPECT--
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bool(true)
bool(true)
