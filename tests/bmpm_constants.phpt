--TEST--
bmpm(): module constants are registered with the documented values
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC, BMPM_APPROX, BMPM_EXACT);
?>
--EXPECT--
int(0)
int(1)
int(2)
int(1)
int(2)
