--TEST--
bmpm(): ashkenazi "vogel" guesses the full language set (upstream ^vogel typo is a no-op)
--EXTENSIONS--
phonetic
--FILE--
<?php
// ash_lang.txt's "^vogel german," row carries an upstream Commons Codec 1.17.1
// typo (trailing comma): "german," names no language, so the rule's mask is
// empty and the guess falls back to "any" -- exactly what the Java oracle
// emits. A german-only guess would return the forced-german set below, so pin
// both to lock the parity in.
var_dump(bmpm("vogel", BMPM_ASHKENAZI, BMPM_APPROX));
var_dump(bmpm("Vogel", BMPM_ASHKENAZI, BMPM_APPROX));
var_dump(bmpm("vogel", BMPM_ASHKENAZI, BMPM_APPROX, "german"));
?>
--EXPECT--
string(9) "fogl|vogl"
string(9) "fogl|vogl"
string(19) "fYgl|fogl|vYgl|vogl"
