--TEST--
bmpm(): ashkenazi "vogel" guesses the full language set (upstream ^vogel typo is a no-op)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Commons Codec 1.17.1's 'german,' typo produces an empty mask and falls
// back to 'any'; correcting it would diverge from the oracle.
var_dump(bmpm("vogel", BMPM_ASHKENAZI, BMPM_APPROX));
var_dump(bmpm("Vogel", BMPM_ASHKENAZI, BMPM_APPROX));
var_dump(bmpm("vogel", BMPM_ASHKENAZI, BMPM_APPROX, "german"));
?>
--EXPECT--
string(9) "fogl|vogl"
string(9) "fogl|vogl"
string(19) "fYgl|fogl|vYgl|vogl"
