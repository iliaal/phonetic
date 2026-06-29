--TEST--
bmpm(): no silent word-count cap (65th+ word still encoded)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Commons Codec imposes no cap on the number of input words. A distinct 65th
// word must change the output (the engine previously dropped words past 64).
$w64 = implode(" ", array_map(fn($i) => "word$i", range(1, 64)));
$w65 = $w64 . " jones";
$w66 = $w65 . " baker";

$o64 = bmpm($w64);
$o65 = bmpm($w65);
$o66 = bmpm($w66);

var_dump($o65 !== $o64);            // 65th word changes the result
var_dump($o66 !== $o65);            // 66th word changes it again
var_dump(bmpm($w65) === $o65);      // deterministic
// the trailing "jones" word contributes its phonemes to every alternative
var_dump(str_contains($o65, "onis") || str_contains($o65, "oniS"));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
