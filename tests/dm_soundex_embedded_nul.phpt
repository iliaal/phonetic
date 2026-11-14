--TEST--
dm_soundex(): embedded NUL bytes end a pattern match without reading past the rule pattern
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach (["s\0ch", "Mos\0kowitz\0", "t\0s\0z", "\0"] as $s) {
    echo bin2hex($s), ' ', implode(',', dm_soundex($s)), "\n";
}
var_dump(dm_soundex_match("s\0ch", "sch"));
?>
--EXPECT--
73006368 400000,450000
4d6f73006b6f7769747a00 645740
740073007a 340000
00 000000
bool(true)
