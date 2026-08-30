--TEST--
dm_soundex(): crafted fork-alternation input cannot grow the live branch set past the cap
--EXTENSIONS--
phonetic
--FILE--
<?php
// Real names fork into a handful of codes and are unaffected by the cap.
var_dump(dm_soundex("Auerbach"));
echo count(dm_soundex("Rosochowaciec")), "\n";

// Finding-4 shape: a short cycle over the '|'-alternative fork letters (the
// Polish/Romanian ogonek/cedilla and c/j rules) drives the live branch set to
// ~1717 distinct codes and burns hundreds of ms of pure CPU on a 4 KB input.
// The per-character sweep is O(|set| x alts), so bounding the set fails hard
// before the CPU cost grows disproportionate to the input (CWE-400).
$e = "\xC4\x99"; // U+0119 e-ogonek
$t = "\xC5\xA3"; // U+0163 t-cedilla
$cyc = str_replace(" ", "", "{$e}j{$e}j{$e}j{$e}{$t}c{$t}c{$t}rsc{$t}crsc{$t}cccc c{$e}j{$e}j{$e}l{$e}j{$e}cc");
dm_soundex(substr(str_repeat($cyc, 200), 0, 4090));
?>
--EXPECTF--
array(2) {
  [0]=>
  string(6) "097400"
  [1]=>
  string(6) "097500"
}
8

Fatal error: dm_soundex(): phonetic: dm_soundex branch set exceeds 128 distinct codes in %s on line %d
