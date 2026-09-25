--TEST--
U+212A KELVIN SIGN folds to ASCII k with Commons Codec parity
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Expected values are pinned from Apache Commons Codec 1.17.1. */
$kelvin = "K\u{212A}elvin";
$braun = "Br\u{212A}un";
$kelvinSign = "\u{212A}";

// The compatibility sign is case-folded wherever it occurs.
var_dump(bmpm($kelvin));
var_dump(bmpm($braun));
var_dump(dm_soundex($kelvin));
var_dump(dm_soundex($braun));

// A standalone sign matches the ASCII K control in both encoders.
var_dump(bmpm($kelvinSign));
var_dump(bmpm("K"));
var_dump(dm_soundex($kelvinSign));
var_dump(dm_soundex("K"));

// The folded spelling matches the equivalent ASCII spelling in either order.
var_dump(bmpm_match($kelvin, "Kelvin"));
var_dump(bmpm_match("Kelvin", $kelvin));
var_dump(dm_soundex_match($kelvin, "Kelvin"));
var_dump(dm_soundex_match("Kelvin", $kelvin));

// A neighboring unassigned code point remains outside the fold table.
var_dump(bmpm("A\u{0378}B"));
var_dump(dm_soundex("A\u{0378}B"));
?>
--EXPECT--
string(13) "kYlvin|kilvin"
string(5) "brkun"
array(1) {
  [0]=>
  string(6) "587600"
}
array(1) {
  [0]=>
  string(6) "795600"
}
string(1) "k"
string(1) "k"
array(1) {
  [0]=>
  string(6) "500000"
}
array(1) {
  [0]=>
  string(6) "500000"
}
bool(true)
bool(true)
bool(true)
bool(true)
string(14) "Yp|af|ap|of|op"
array(1) {
  [0]=>
  string(6) "070000"
}
