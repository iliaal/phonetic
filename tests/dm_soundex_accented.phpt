--TEST--
dm_soundex(): accent folding and Unicode rule patterns
--EXTENSIONS--
phonetic
--FILE--
<?php
// Müller≡Mueller without a ü=u fold in dmrules (upstream Commons Codec data
// has ù/ú/û but not ü). Mid-word ü is skipped (no rule) and mid-word "ue"
// codes empty under default, so both yield the same digit stream. Initial Ü
// does NOT match U (Über ≠ Uber) — pinned below as a data limitation.
var_dump(dm_soundex("Müller") === dm_soundex("Mueller"));
var_dump(dm_soundex("Über") === dm_soundex("Uber"));
// Polish: l-stroke folds to l, e-ogonek branches (default code "|6").
var_dump(dm_soundex("Wałęsa"));
// Romanian s-comma is dropped (no rule, no folding); same as "Ceauescu".
var_dump(dm_soundex("Ceauşescu"));
?>
--EXPECT--
bool(true)
bool(false)
array(2) {
  [0]=>
  string(6) "784000"
  [1]=>
  string(6) "786400"
}
array(2) {
  [0]=>
  string(6) "440000"
  [1]=>
  string(6) "540000"
}
