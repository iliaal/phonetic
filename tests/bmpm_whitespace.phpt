--TEST--
bmpm(): leading/internal/trailing whitespace matches Commons Codec
--EXTENSIONS--
phonetic
--FILE--
<?php
// Golden values from Apache Commons Codec 1.17.1 (the parity oracle).
// Leading whitespace and runs of internal whitespace are trimmed/collapsed.
// Trailing whitespace is NOT equivalent to the trimmed form: guessLanguages()
// runs on the raw, untrimmed input (verified against the reference), so a
// trailing space prevents a "$"-anchored language rule from matching and
// widens the guessed language set -- the reference yields "zmi|zmit" too.
var_dump(bmpm("smith")        === "zmit");          // baseline
var_dump(bmpm(" smith")       === "zmit");          // leading space trimmed
var_dump(bmpm("\tsmith")      === "zmit");          // leading tab trimmed
var_dump(bmpm("  smith")      === "zmit");          // repeated leading ws
var_dump(bmpm("smith ")       === "zmi|zmit");      // trailing space: matches Commons
var_dump(bmpm("smith\t")      === "zmi|zmit");      // trailing tab: matches Commons
var_dump(bmpm("smith  ")      === bmpm("smith "));  // repeated trailing ws stable
var_dump(bmpm("a b")          === bmpm("a  b"));    // internal run collapses
var_dump(bmpm("a b")          === bmpm("a b "));    // ... independent of trailing
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
