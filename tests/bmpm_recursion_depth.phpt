--TEST--
bmpm(): deep d'/prefix recursion is depth-bounded (no native stack overflow) and budget-bounded
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Before the BMPM_MAX_PREFIX_DEPTH cap, "d" followed by many apostrophes drove
 * O(input-length) native C recursion and crashed the process. The cap now
 * bounds the recursion to a fixed depth, so the output is a deterministic
 * string -- and adding more apostrophes past the cap cannot change it (extra
 * levels are encoded inline once the depth limit is hit). Pinning the exact
 * value guards the clamp: a wrong depth bound would change the string, not
 * merely still "return a string". (A much longer apostrophe run is safely
 * rejected by the per-encode budget, like the deep prefix chain below.) */
$aposA = bmpm("d" . str_repeat("'", 100));
$aposB = bmpm("d" . str_repeat("'", 200));
var_dump($aposA);
var_dump($aposA === $aposB);   // depth cap makes extra apostrophes inert

/* Space-separated prefix path ("de " repeated) past the depth cap: the cap
 * bounds the recursion, so a moderate chain stays a fixed-length deterministic
 * string well within the per-encode work budget. */
$deep_prefix = str_repeat("de ", 50) . "cohen";
var_dump(strlen($deep_prefix) <= 4096);   // input is within the byte cap
var_dump(strlen(bmpm($deep_prefix)));

/* Ordinary input is unaffected by the cap or the budget. */
var_dump(bmpm("Jackson"));

/* An extreme prefix chain (1000 words) that the depth cap alone would still let
 * re-guess the language of a multi-KB input at every recursion node is now
 * rejected by the per-encode budget instead (CWE-400). At the default (auto)
 * language it is the language-guess dimension that fills first. Last statement:
 * the fatal halts. */
bmpm(str_repeat("de ", 1000) . "cohen");
?>
--EXPECTF--
string(32) "()-(()-(()-(()-(()-(()-(|t))))))"
bool(true)
bool(true)
int(13883)
string(77) "iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson"

Fatal error: bmpm(): phonetic: BMPM encode exceeds language-guess budget in %s on line %d
