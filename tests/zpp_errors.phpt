--TEST--
all functions: zero-arg and wrong-type calls raise the standard ZPP errors
--EXTENSIONS--
phonetic
--FILE--
<?php
$unary  = ["double_metaphone", "bmpm", "dm_soundex", "nysiis", "match_rating"];
$binary = ["double_metaphone_match", "bmpm_match", "dm_soundex_match",
           "nysiis_match", "match_rating_compare"];

foreach (array_merge($unary, $binary) as $fn) {
    try { $fn(); } catch (\Throwable $e) { echo $fn, " zero: ", get_class($e), "\n"; }
}
foreach ($unary as $fn) {
    try { $fn([]); } catch (\Throwable $e) { echo $fn, " array: ", get_class($e), "\n"; }
}
foreach ($binary as $fn) {
    try { $fn([], "x"); } catch (\Throwable $e) { echo $fn, " array: ", get_class($e), "\n"; }
}
?>
--EXPECT--
double_metaphone zero: ArgumentCountError
bmpm zero: ArgumentCountError
dm_soundex zero: ArgumentCountError
nysiis zero: ArgumentCountError
match_rating zero: ArgumentCountError
double_metaphone_match zero: ArgumentCountError
bmpm_match zero: ArgumentCountError
dm_soundex_match zero: ArgumentCountError
nysiis_match zero: ArgumentCountError
match_rating_compare zero: ArgumentCountError
double_metaphone array: TypeError
bmpm array: TypeError
dm_soundex array: TypeError
nysiis array: TypeError
match_rating array: TypeError
double_metaphone_match array: TypeError
bmpm_match array: TypeError
dm_soundex_match array: TypeError
nysiis_match array: TypeError
match_rating_compare array: TypeError
