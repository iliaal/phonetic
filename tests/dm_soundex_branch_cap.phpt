--TEST--
dm_soundex(): crafted fork-alternation input cannot grow the live branch set past the cap
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(dm_soundex("Auerbach"));
echo count(dm_soundex("Rosochowaciec")), "\n";

// Repeated fork rules saturate the live set; retain the first 128 branches.
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
