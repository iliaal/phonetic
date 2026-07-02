--TEST--
bmpm(): control characters <= U+0020 are trimmed like spaces
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm("\x1fvogel") === bmpm(" vogel"));
var_dump(bmpm("\x1fvogel") === bmpm("vogel"));
?>
--EXPECT--
bool(true)
bool(true)
