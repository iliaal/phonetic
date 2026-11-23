--TEST--
bmpm(), bmpm_match(): $name_type accepts exactly the enum range
--EXTENSIONS--
phonetic
--FILE--
<?php
// Each side of the valid range plus the zend_long extremes. Only values that
// are ints on 32-bit PHP too, so the expected output is the same everywhere.
$values = [PHP_INT_MIN, PHP_INT_MIN + 1, -2, -1,
           BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC, 3, 4,
           PHP_INT_MAX - 1, PHP_INT_MAX];
foreach ($values as $nt) {
    foreach ([fn() => bmpm("Jackson", $nt), fn() => bmpm_match("Jackson", "Jakson", $nt)] as $fn) {
        try {
            echo var_export($fn(), true), "\n";
        } catch (\ValueError $e) {
            echo $e->getMessage(), "\n";
        }
    }
}
?>
--EXPECT--
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
'iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson'
true
'iYgzon|iYkson|iagzon|iakson|iaksun|iatskson|iatsksun|iinkson|iinksun|iintskson|iintsksun|iogzon|iokson|ioksun|ionkson|ionksun|iontskson|iontsksun|iotskson|iotsksun|xakson|xokson|zakson|zokson'
true
'haksun|zaksun'
true
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm(): Argument #2 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
bmpm_match(): Argument #3 ($name_type) must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC
