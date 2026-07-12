--TEST--
bmpm(): deep d'/prefix recursion is depth-bounded (no native stack overflow)
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Before the BMPM_MAX_PREFIX_DEPTH cap, "d" followed by thousands of
 * apostrophes drove O(input-length) native C recursion and crashed the
 * process. The cap now bounds the recursion to a fixed depth, so the output is
 * a deterministic string -- and adding more apostrophes past the cap cannot
 * change it (extra levels are encoded inline once the depth limit is hit).
 * Pinning the exact value guards the clamp: a wrong depth bound would change
 * the string, not merely still "return a string". */
$apos1000 = bmpm("d" . str_repeat("'", 1000));
$apos2000 = bmpm("d" . str_repeat("'", 2000));
var_dump($apos1000);
var_dump($apos1000 === $apos2000);   // depth cap makes extra apostrophes inert

/* Space-separated prefix path ("de " repeated): the depth cap bounds the
 * recursion, so the deterministic result stays at a fixed length. */
$deep_prefix = str_repeat("de ", 1000) . "cohen";
var_dump(strlen($deep_prefix) <= 4096);   // input is within the byte cap
var_dump(strlen(bmpm($deep_prefix)));

/* Ordinary input is unaffected by the cap. */
var_dump(bmpm("Jackson"));
echo "survived\n";
?>
--EXPECT--
string(82) "()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(()-(|t))))))))))))))))"
bool(true)
bool(true)
int(676323)
string(77) "iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson"
survived
