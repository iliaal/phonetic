--TEST--
Latin-Extended letters outside the fold tables (U+1E9E, U+0130): pinned behavior
--EXTENSIONS--
phonetic
--FILE--
<?php
/* U+1E9E and U+0130 are dropped: oracle parity for Double Metaphone,
 * deliberate ASCII-only divergence for MRA and NYSIIS.
 * Double Metaphone and MRA expand lowercase ß. */
$sharp  = "STRA\u{1E9E}E";     // STRAẞE
$dotted = "\u{0130}stanbul";   // İstanbul

// double_metaphone: parity with Commons (both drop the code point)
var_dump(double_metaphone($sharp)["primary"]);
var_dump(double_metaphone($dotted)["primary"]);

// match_rating: deliberate ASCII-only drop (Commons keeps the raw char)
var_dump(match_rating($sharp));
var_dump(match_rating($dotted));

// nysiis: same ASCII-only drop
var_dump(nysiis($sharp));
var_dump(nysiis($dotted));

// lowercase ß maps to SS in both Commons and here
var_dump(double_metaphone("Stra\u{00DF}e")["primary"]);
var_dump(match_rating("STRA\u{00DF}E"));
?>
--EXPECT--
string(3) "STR"
string(4) "STNP"
string(3) "STR"
string(5) "STNBL"
string(3) "STR"
string(6) "STANBA"
string(4) "STRS"
string(4) "STRS"
