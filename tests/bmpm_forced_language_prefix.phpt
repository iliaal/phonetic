--TEST--
bmpm(): forced language threads through the name-prefix branch
--EXTENSIONS--
phonetic
--FILE--
<?php
// Oracle-verified (Apache Commons Codec 1.17.1): polish-forced "Smith".
var_dump(bmpm("Smith", BMPM_GENERIC, BMPM_APPROX, "polish"));
// The prefix branch must keep the forced language instead of re-guessing:
// both groups carry the polish forms (zmQtx/zmitx), not the auto-detected set.
echo bmpm("van Smith", BMPM_GENERIC, BMPM_APPROX, "polish"), "\n";
// ... and therefore differs from the unforced encoding.
var_dump(bmpm("van Smith", BMPM_GENERIC, BMPM_APPROX, "polish") !== bmpm("van Smith"));
?>
--EXPECT--
string(16) "zmQtx|zmit|zmitx"
(zmQtx|zmit|zmitx)-(vanzmQtx|vanzmit|vanzmitx|vonzmQtx|vonzmit|vonzmitx|zmQtx|zmit|zmitx)
bool(true)
