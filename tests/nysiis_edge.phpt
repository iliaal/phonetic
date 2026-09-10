--TEST--
nysiis(): empty/non-letter input and max_length truncation
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(nysiis(""));
var_dump(nysiis("123 !@#"));
var_dump(nysiis(" "));
var_dump(nysiis("o'brien"));
var_dump(nysiis("Westerlund"));
var_dump(nysiis("Westerlund", 0));
var_dump(nysiis("Westerlund", 3));
?>
--EXPECT--
string(0) ""
string(0) ""
string(0) ""
string(5) "OBRAN"
string(6) "WASTAR"
string(9) "WASTARLAD"
string(3) "WAS"
