--TEST--
bmpm(): Cyrillic-script input is lowercased and matches Commons Codec
--EXTENSIONS--
phonetic
--FILE--
<?php
echo bmpm("Иванов"), "\n";
echo bmpm("Петров"), "\n";
echo bmpm("Смирнов"), "\n";
?>
--EXPECT--
Qvanof|Qvonof|ivanof|ivonof
pYtrof|pitrof
zmQrnof|zmirnof
