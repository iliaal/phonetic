--TEST--
dm_soundex_match(): true when the Daitch-Mokotoff code sets intersect
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(dm_soundex_match("Moskowitz", "Moskovitz")); // same code
var_dump(dm_soundex_match("Peterson", "Peters"));      // disjoint
var_dump(dm_soundex_match("Smith", "Xylophone"));      // disjoint
var_dump(dm_soundex_match("", ""));                     // empty -> false
var_dump(dm_soundex_match("Katz", "Katz"));            // identical
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
