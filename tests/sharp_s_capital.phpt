--TEST--
capital sharp S (U+1E9E) folds like ß in the lowercase-path engines (BMPM, DM-Soundex)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Commons Codec lower-cases before BMPM / Daitch-Mokotoff rules, and
// Character.toLowerCase(U+1E9E) is ß (U+00DF). The capital sharp S must encode
// identically to the small sharp s in those engines.
$lower = "Stra\u{00DF}e";   // Straße
$upper = "STRA\u{1E9E}E";   // STRAẞE

var_dump(dm_soundex($upper) === dm_soundex($lower));
var_dump(bmpm($upper) === bmpm($lower));
var_dump(dm_soundex_match($upper, $lower));
var_dump(bmpm_match($upper, $lower));

// Oracle (Commons Codec 1.17.1) values, for a concrete anchor.
echo implode(",", dm_soundex($upper)), "\n";
echo bmpm($upper), "\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
294000
strYsi|strasi|strosi
