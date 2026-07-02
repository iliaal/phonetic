--TEST--
bmpm(): forced language with ashkenazi and sephardic name types
--EXTENSIONS--
phonetic
--FILE--
<?php
// Golden values from Apache Commons Codec 1.17.1 (the parity oracle).
var_dump(bmpm("Cohen", BMPM_ASHKENAZI, BMPM_APPROX, "russian"));
var_dump(bmpm("Garcia", BMPM_SEPHARDIC, BMPM_APPROX, "spanish"));
?>
--EXPECT--
string(4) "koin"
string(6) "garzia"
