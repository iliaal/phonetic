--TEST--
bmpm(): module constants are registered with the documented values
--EXTENSIONS--
phonetic
--FILE--
<?php
// Accuracy values (10/20) are deliberately disjoint from the name-type values
// (0/1/2) so a misplaced constant is rejected instead of silently accepted.
var_dump(BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC, BMPM_APPROX, BMPM_EXACT);
?>
--EXPECT--
int(0)
int(1)
int(2)
int(10)
int(20)
