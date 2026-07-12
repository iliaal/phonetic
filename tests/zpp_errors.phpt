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

// Wrong type for the 2nd argument of every binary comparison function.
foreach ($binary as $fn) {
    try { $fn("x", []); } catch (\Throwable $e) { echo $fn, " arg2: ", get_class($e), "\n"; }
}

// Wrong type for the optional trailing arguments (arrays never coerce to the
// int/string these expect, so each is a TypeError). Covers max_length as well
// as the BMPM name_type / accuracy / language triple.
$typed = [
    "double_metaphone max_length"       => fn() => double_metaphone("x", []),
    "nysiis max_length"                 => fn() => nysiis("x", []),
    "double_metaphone_match max_length" => fn() => double_metaphone_match("x", "y", []),
    "nysiis_match max_length"           => fn() => nysiis_match("x", "y", []),
    "bmpm name_type"                    => fn() => bmpm("x", []),
    "bmpm accuracy"                     => fn() => bmpm("x", BMPM_GENERIC, []),
    "bmpm language"                     => fn() => bmpm("x", BMPM_GENERIC, BMPM_APPROX, []),
    "bmpm_match name_type"              => fn() => bmpm_match("x", "y", []),
    "bmpm_match accuracy"               => fn() => bmpm_match("x", "y", BMPM_GENERIC, []),
    "bmpm_match language"               => fn() => bmpm_match("x", "y", BMPM_GENERIC, BMPM_APPROX, []),
];
foreach ($typed as $label => $fn) {
    try { $fn(); } catch (\Throwable $e) { echo $label, ": ", get_class($e), "\n"; }
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
double_metaphone_match arg2: TypeError
bmpm_match arg2: TypeError
dm_soundex_match arg2: TypeError
nysiis_match arg2: TypeError
match_rating_compare arg2: TypeError
double_metaphone max_length: TypeError
nysiis max_length: TypeError
double_metaphone_match max_length: TypeError
nysiis_match max_length: TypeError
bmpm name_type: TypeError
bmpm accuracy: TypeError
bmpm language: TypeError
bmpm_match name_type: TypeError
bmpm_match accuracy: TypeError
bmpm_match language: TypeError
