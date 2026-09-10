--TEST--
match_rating(): single-char and non-ASCII parity (byte-vs-char guard, ß expansion)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Count characters, not bytes, for the trivial-input guard.
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
// Different raw strings bypass the equality shortcut and exercise expansion.
var_dump(match_rating_compare(str_repeat("\xDF", 23), str_repeat("\xDF", 22) . "S"));
// Commons Codec rates SXSN vs ST at or above the match threshold.
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
