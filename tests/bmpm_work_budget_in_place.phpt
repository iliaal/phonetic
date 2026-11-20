--TEST--
bmpm(): phonemes extended in place charge the work budget like copied ones
--EXTENSIONS--
phonetic
--FILE--
<?php
// The work budget trips first at 225 repetitions of "kowski" (Ashkenazi),
// the same boundary as before single-alternative rules were applied in place.
// Without in-place charges the first failure moves to 336 repetitions.
foreach ([224, 225] as $n) {
    try {
        $r = bmpm(str_repeat('kowski', $n), BMPM_ASHKENAZI);
        echo $n, ': ', strlen($r) > 0 ? 'encoded' : 'empty', "\n";
    } catch (ValueError $e) {
        echo $n, ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
224: encoded
225: phonetic: BMPM encode exceeds work budget
