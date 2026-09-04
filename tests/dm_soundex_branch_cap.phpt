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
// The per-character sweep is O(|set| x alts), so bounding the set keeps the
// first 128 distinct codes in insertion order, drops later ones, and keeps
// encoding (CWE-400 backstop without failing the call).
$e = "\xC4\x99"; // U+0119 e-ogonek
$t = "\xC5\xA3"; // U+0163 t-cedilla
$cyc = str_replace(" ", "", "{$e}j{$e}j{$e}j{$e}{$t}c{$t}c{$t}rsc{$t}crsc{$t}cccc c{$e}j{$e}j{$e}l{$e}j{$e}cc");
$r = dm_soundex(substr(str_repeat($cyc, 200), 0, 4090));
echo count($r), "\n";
echo $r[0], "\n";
echo $r[127], "\n";
?>
--EXPECT--
array(2) {
  [0]=>
  string(6) "097400"
  [1]=>
  string(6) "097500"
}
8
128
343434
345866
