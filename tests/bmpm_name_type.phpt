--TEST--
bmpm(): name type selects generic, ashkenazi or sephardic rule sets
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm("Moskowitz", BMPM_GENERIC));
var_dump(bmpm("Moskowitz", BMPM_ASHKENAZI));
var_dump(bmpm("Garcia", BMPM_SEPHARDIC));
?>
--EXPECT--
string(39) "mYskovitS|mYskovits|moskovitS|moskovits"
string(39) "mYskovitS|mYskovits|moskovitS|moskovits"
string(13) "gartsa|garzia"
