--TEST--
bmpm(): leading/internal/trailing whitespace matches Commons Codec
--EXTENSIONS--
phonetic
--FILE--
<?php
// Commons Codec 1.17.1 guesses before trimming: trailing whitespace blocks
// a '$'-anchored rule and widens the result to zmi|zmit.
var_dump(bmpm("smith")        === "zmit");
var_dump(bmpm(" smith")       === "zmit");
var_dump(bmpm("\tsmith")      === "zmit");
var_dump(bmpm("  smith")      === "zmit");
var_dump(bmpm("smith ")       === "zmi|zmit");
var_dump(bmpm("smith\t")      === "zmi|zmit");
var_dump(bmpm("smith  ")      === bmpm("smith "));
var_dump(bmpm("a b")          === bmpm("a  b"));
var_dump(bmpm("a b")          === bmpm("a b "));
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
