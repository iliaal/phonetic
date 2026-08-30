--TEST--
bmpm(): multi-separator prefix fan-out cannot run unbudgeted language-guess scans
--EXTENSIONS--
phonetic
--FILE--
<?php
// A real nested-prefix name is guessed a handful of times and is unaffected.
var_dump(is_string(bmpm("de la cruz")));

// A 6-separator x 6-prefix chain fans out to ~127 recursive children, each of
// which re-runs bm_guess_languages -- an O(input x rules) scan -- BEFORE any
// phonemes are produced. With an UNMATCHED payload ('~' matches no phoneme
// rule) the phoneme-count/byte/copy caps barely move, yet the 127 guesses over
// a ~4 KB input are real CPU. That language-guess work is now charged to the
// same per-encode budget, so the fan-out fails hard rather than burning CPU
// disproportionate to the input (CWE-400). Last statement: the fatal halts.
$sep = str_repeat("-", 6);
bmpm(str_repeat("de" . $sep, 6) . str_repeat("~", 4048));
?>
--EXPECTF--
bool(true)

Fatal error: bmpm(): phonetic: BMPM encode exceeds language-guess budget in %s on line %d
