--TEST--
match helpers: empty or unencodable input never matches
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm_match("Smith", ""));
var_dump(dm_soundex_match("Katz", ""));
var_dump(nysiis_match("Smith", ""));
var_dump(match_rating_compare("", ""));
// Identical raw inputs short-circuit to true before cleaning (oracle
// equalsIgnoreCase parity); a non-identical pair that cleans to nothing
// hits the cleaned-empty guard and is false, not a crash.
var_dump(match_rating_compare(".,-", ".,-"));
var_dump(match_rating_compare(".,-", "-,."));
// Symmetric: an empty side yields 0 regardless of position.
var_dump(double_metaphone_match("", "x"));
var_dump(double_metaphone_match("x", ""));
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
bool(false)
int(0)
int(0)
