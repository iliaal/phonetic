--TEST--
bmpm_match(): prefix-branched (A)-(B) outputs still intersect on the bare name
--EXTENSIONS--
phonetic
--FILE--
<?php
// "van Smith" encodes as (smith-tokens)-(vansmith-tokens); the match helper
// must split on ()|- so the bare-name tokens cross with plain "Smith".
var_dump(bmpm_match("van Smith", "Smith"));
var_dump(bmpm_match("d'Angelo", "Angelo"));
var_dump(bmpm_match("van Smith", "Jones"));
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
