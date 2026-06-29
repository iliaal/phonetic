--TEST--
bmpm(): forcing a language skips auto-detection
--EXTENSIONS--
phonetic
--FILE--
<?php
// Auto-detection keeps several candidate readings; forcing a single language
// narrows the rule set and prunes the alternatives.
var_dump(bmpm("Alpert", BMPM_GENERIC, BMPM_APPROX));
var_dump(bmpm("Alpert", BMPM_GENERIC, BMPM_APPROX, "russian"));
var_dump(bmpm("Alpert", BMPM_GENERIC, BMPM_APPROX, "polish"));
?>
--EXPECT--
string(53) "YlpYrt|Ylpirt|alpYrt|alpir|alpirt|olpYrt|olpir|olpirt"
string(27) "alpYrt|alpirt|olpYrt|olpirt"
string(27) "alpYrt|alpirt|olpYrt|olpirt"
