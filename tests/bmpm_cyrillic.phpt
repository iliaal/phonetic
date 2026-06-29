--TEST--
bmpm(): Cyrillic-script input is lowercased and matches Commons Codec
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Uppercase Cyrillic is now lowercased before rule matching, so raw
 * Cyrillic-script names encode the same as Apache Commons Codec. */
echo bmpm("Иванов"), "\n";
echo bmpm("Петров"), "\n";
echo bmpm("Смирнов"), "\n";
?>
--EXPECT--
Qvanof|Qvonof|ivanof|ivonof
pYtrof|pitrof
zmQrnof|zmirnof
