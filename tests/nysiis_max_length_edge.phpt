--TEST--
nysiis(): negative max_length means full code; match respects explicit length
--EXTENSIONS--
phonetic
--FILE--
<?php
// Any max_length <= 0 is the "no truncation" contract.
var_dump(nysiis("Westerlund", -1) === nysiis("Westerlund", 0));
var_dump(nysiis("Westerlund", 0));
// Codes agree on the first 6 chars but diverge in the full form...
var_dump(nysiis("Westerberg", 0));
// ...so the pair matches at the strict length but not at full length.
var_dump(nysiis_match("Westerlund", "Westerberg", 6));
var_dump(nysiis_match("Westerlund", "Westerberg", 0));
?>
--EXPECT--
bool(true)
string(9) "WASTARLAD"
string(10) "WASTARBARG"
bool(true)
bool(false)
