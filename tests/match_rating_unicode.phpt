--TEST--
match_rating(): single-char and non-ASCII parity (byte-vs-char guard, ß expansion)
--EXTENSIONS--
phonetic
--FILE--
<?php
// A single character (even multi-byte) is trivial input -> no code,
// matching the reference's name.length() == 1 short-circuit.
var_dump(match_rating("é"));
var_dump(match_rating("ñ"));
var_dump(match_rating("A"));
// Two single-character names never compare, even when identical.
var_dump(match_rating_compare("é", "é"));
var_dump(match_rating_compare("é", "è"));
// ß upper-cases to "SS" before accent folding (reference cleanName order).
var_dump(match_rating("Straße"));   // STRS
// Malformed UTF-8 falls back to Latin-1; bare 0xDF still expands to SS safely.
var_dump(match_rating(str_repeat("\xDF", 23)));
// Compare must also survive that expansion; use different raw strings so the
// raw case-insensitive equality shortcut cannot bypass encoding.
var_dump(match_rating_compare(str_repeat("\xDF", 23), str_repeat("\xDF", 22) . "S"));
// Faithful-to-reference tail processing (verified against the Commons Codec
// algorithm): codes SXSN vs ST clear the rating threshold.
var_dump(match_rating_compare("SAXSON", "SAT"));
?>
--EXPECT--
string(0) ""
string(0) ""
string(0) ""
bool(false)
bool(false)
string(4) "STRS"
string(6) "SSSSSS"
bool(true)
bool(true)
