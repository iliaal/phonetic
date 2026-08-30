--TEST--
bmpm(): crafted super-linear phoneme-set expansion is bounded by the per-encode work budget
--EXTENSIONS--
phonetic
--FILE--
<?php
// Oracle-faithful behaviour is preserved for real inputs. The multi-space
// prefix fan-out mirrors Commons Codec exactly (the combined child re-fires the
// same prefix, one separator char per level), and '-' maps to ' ', so the
// budget must NOT change any legitimate result -- it only rejects pathological
// input disproportionate to its size.
echo bmpm("de  la cohen"), "\n";
var_dump(bmpm("de la cohen") === bmpm("de-la-cohen"));

// A crafted <=4096-byte input that saturates the three-pass expansion
// (20 -> <=400 -> <=8000 phonemes, ~12 MB of text) now fails hard instead of
// returning a phoneme string thousands of times larger than the input
// (CWE-400). The fatal halts execution, so it is the last statement.
bmpm(str_repeat("aves", 1024), BMPM_ASHKENAZI);
?>
--EXPECTF--
(lYkYin|lYkoin|lakYin|lakoin|lokYin|lokoin)-((lYkYin|lYkoin|lakYin|lakoin|lokYin|lokoin)-((kYin|koin)-(dYlYkYin|dYlYkoin|dYlakYin|dYlakoin|dYlokYin|dYlokoin|dilYkYin|dilYkoin|dilakYin|dilakoin|dilokYin|dilokoin)))
bool(true)

Fatal error: bmpm(): phonetic: BMPM encode exceeds work budget in %s on line %d
