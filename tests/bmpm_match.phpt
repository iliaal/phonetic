--TEST--
bmpm_match(): true when the Beider-Morse phoneme sets intersect
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm_match("Jackson", "Jaxon"));
var_dump(bmpm_match("Mueller", "Miller"));
var_dump(bmpm_match("Smith", "Xylophone"));
var_dump(bmpm_match("", ""));
// accuracy is forwarded: this pair intersects under APPROX but not EXACT
var_dump(bmpm_match("Peterson", "Petersen", BMPM_GENERIC, BMPM_APPROX));
var_dump(bmpm_match("Peterson", "Petersen", BMPM_GENERIC, BMPM_EXACT));
// name_type + language forwarded (identical inputs still match under forced set)
var_dump(bmpm_match("Peterson", "Peterson", BMPM_GENERIC, BMPM_EXACT, "english"));
// Ashkenazi path still intersects for this classic pair
var_dump(bmpm_match("Moskowitz", "Moskovitz", BMPM_ASHKENAZI));
// Sephardic path: identity, non-identity true, and distant false
var_dump(bmpm_match("Garcia", "Garcia", BMPM_SEPHARDIC));
var_dump(bmpm_match("Garcia", "Garciaa", BMPM_SEPHARDIC));
var_dump(bmpm_match("Garcia", "Xylophone", BMPM_SEPHARDIC));
// Forced language changes a non-identity comparison (not identity short-circuit).
// Nelson/Nilsson intersect under auto-detect but not under forced english.
var_dump(bmpm_match("Nelson", "Nilsson"));
var_dump(bmpm_match("Nelson", "Nilsson", BMPM_GENERIC, BMPM_APPROX, "english"));
// "any" is rejected (not a forced language)
try {
    bmpm_match("Smith", "Smith", BMPM_GENERIC, BMPM_APPROX, "any");
    echo "any_ok\n";
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bmpm_match(): Argument #5 ($language) "any" is not a forced language; pass an empty string for auto-detect
