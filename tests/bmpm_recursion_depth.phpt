--TEST--
bmpm(): deep d'/prefix recursion is depth-bounded (no native stack overflow) and budget-bounded
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Extra apostrophes become inert beyond the recursion cap. */
$aposA = bmpm("d" . str_repeat("'", 100));
$aposB = bmpm("d" . str_repeat("'", 200));
var_dump($aposA);
var_dump($aposA === $aposB);   // depth cap makes extra apostrophes inert

/* A moderate prefix chain fits the work budget past the depth cap. */
$deep_prefix = str_repeat("de ", 50) . "cohen";
var_dump(strlen($deep_prefix) <= 4096);   // input is within the byte cap
var_dump(strlen(bmpm($deep_prefix)));

var_dump(bmpm("Jackson"));

/* Depth alone cannot bound repeated language guesses; this exhausts that budget. */
bmpm(str_repeat("de ", 1000) . "cohen");
?>
--EXPECTF--
string(32) "()-(()-(()-(()-(()-(()-(|t))))))"
bool(true)
bool(true)
int(13883)
string(77) "iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson"

Fatal error: bmpm(): phonetic: BMPM encode exceeds language-guess budget in %s on line %d
