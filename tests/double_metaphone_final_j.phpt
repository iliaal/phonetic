--TEST--
double_metaphone(): word-final J emits space in alternate (do not trim for match)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Published Philips rule: word-final J yields primary "J" and alternate " "
// (a single space). Match helpers must compare codes as returned.
$c = double_metaphone("Raj", 0);
var_dump($c["primary"]);
var_dump($c["alternate"]);
var_dump(bin2hex($c["alternate"])); // 5220 = "R" + space
// SAN JOSE Spanish arm: no double-J skip on the JOSE/SAN path
$s = double_metaphone("SAN JOSE", 0);
var_dump($s["primary"]);
var_dump($s["alternate"]);
?>
--EXPECT--
string(2) "RJ"
string(2) "R "
string(4) "5220"
string(4) "SNHS"
string(4) "SNHS"
