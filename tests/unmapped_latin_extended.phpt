--TEST--
Latin-Extended letters outside the fold tables (U+1E9E, U+0130): pinned behavior
--EXTENSIONS--
phonetic
--FILE--
<?php
/* U+1E9E (ẞ, capital sharp S) and U+0130 (İ, Turkish dotted capital I) sit
 * outside the Latin-1 fold tables. Pin the behavior so it cannot be "fixed"
 * into a parity regression:
 *   - double_metaphone MATCHES Apache Commons Codec: Java's toUpperCase leaves
 *     both code points unchanged and the encoder then skips them, so folding
 *     ẞ->SS or İ->I would DIVERGE from the parity oracle.
 *   - match_rating and nysiis DELIBERATELY diverge from Commons: Commons keeps
 *     the raw multibyte character in the codex (MRA "STRAẞE" -> "STRẞ"); we drop
 *     non-ASCII per the documented ASCII-only contract ("STRAẞE" -> "STR").
 *   - Lowercase ß IS handled: Commons uppercases ß->SS and we match it. */
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
