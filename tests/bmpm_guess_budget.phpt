--TEST--
bmpm(): multi-separator prefix fan-out cannot run unbudgeted language-guess scans
--EXTENSIONS--
phonetic
--FILE--
<?php
echo bmpm("de la cruz"), "\n";

// '~' matches no phoneme rule, isolating language-guess work.
// Six separators let each prefix re-fire across the 64-leaf recursion tree.
$sep = str_repeat("-", 6);
try {
    bmpm(str_repeat("de" . $sep, 6) . str_repeat("~", 4048));
} catch (ValueError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
(lakru|lakruS|lakrus|lakruts|latzrus|lazrus|lokru|lokruS|lokrus|lokruts|lotzrus|lozrus)-((kru|kruS|krus|kruts|tzrus|zrus)-(dilakru|dilakruS|dilakrus|dilakruts|dilatzrus|dilazrus|dilokru|dilokruS|dilokrus|dilokruts|dilotzrus|dilozrus))
phonetic: BMPM encode exceeds language-guess budget
