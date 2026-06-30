--TEST--
nysiis_match(): true when the NYSIIS keys are equal
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(nysiis_match("Smith", "Schmit"));       // SNAT  == SNAT
var_dump(nysiis_match("Cory", "Corey"));         // CARY  == CARY
var_dump(nysiis_match("Smith", "Jones"));        // SNAT  != JAN
var_dump(nysiis_match("", ""));                  // no key -> false
var_dump(nysiis_match("Macdonald", "Mcdonald")); // strict (6) collapses both
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
