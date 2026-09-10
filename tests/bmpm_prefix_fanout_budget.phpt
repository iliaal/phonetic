--TEST--
bmpm(): GENERIC prefix fan-out shares one work budget across all 2^depth leaves
--EXTENSIONS--
phonetic
--FILE--
<?php
// Force a language to isolate phoneme-copy work from language guessing.
var_dump(is_string(bmpm("van der berg", BMPM_GENERIC, BMPM_APPROX, "english")));

// One leaf fits the 32 MiB work budget.
$payload = str_repeat("aves", 300);
var_dump(is_string(bmpm($payload, BMPM_GENERIC, BMPM_APPROX, "english")));

// Six separators let each prefix re-fire, allowing 64 leaf encodes.
// Their aggregate copies exceed the shared budget even though one leaf fits.
$sep = str_repeat("-", 6);
bmpm(str_repeat("de" . $sep, 6) . $payload, BMPM_GENERIC, BMPM_APPROX, "english");
?>
--EXPECTF--
bool(true)
bool(true)

Fatal error: bmpm(): phonetic: BMPM encode exceeds work budget in %s on line %d
