--TEST--
bmpm(): approx versus exact accuracy and ashkenazi/sephardic exact
--EXTENSIONS--
phonetic
--FILE--
<?php
var_dump(bmpm("Jackson", BMPM_GENERIC, BMPM_APPROX));
var_dump(bmpm("Jackson", BMPM_GENERIC, BMPM_EXACT));
var_dump(bmpm("Schmidt", BMPM_GENERIC, BMPM_EXACT));
var_dump(bmpm("Cohen", BMPM_ASHKENAZI, BMPM_EXACT));
var_dump(bmpm("Garcia", BMPM_SEPHARDIC, BMPM_EXACT));
?>
--EXPECT--
string(77) "iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson"
string(30) "Zakson|dZakson|jakson|jatskson"
string(11) "Smit|StSmit"
string(4) "koen"
string(13) "garsia|gartSa"
