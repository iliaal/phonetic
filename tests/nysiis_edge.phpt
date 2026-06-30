--TEST--
nysiis(): empty/non-letter input and max_length truncation
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(nysiis(""));
var_dump(nysiis("123 !@#"));
var_dump(nysiis(" "));
// non-letters are stripped, letters kept
var_dump(nysiis("o'brien"));
// truncation: default 6 vs explicit lengths vs full
var_dump(nysiis("Westerlund"));        // strict, capped at 6
var_dump(nysiis("Westerlund", 0));     // full
var_dump(nysiis("Westerlund", 3));     // capped at 3
?>
--EXPECT--
string(0) ""
string(0) ""
string(0) ""
string(5) "OBRAN"
string(6) "WASTAR"
string(9) "WASTARLAD"
string(3) "WAS"
