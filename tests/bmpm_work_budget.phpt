--TEST--
bmpm(): crafted super-linear phoneme-set expansion is bounded by the per-encode work budget
--EXTENSIONS--
phonetic
--FILE--
<?php
// Repeated separators re-fire the prefix; hyphens become spaces.
echo bmpm("de  la cohen"), "\n";
var_dump(bmpm("de la cohen") === bmpm("de-la-cohen"));

// Three-pass expansion of this 4096-byte payload exceeds the work budget.
bmpm(str_repeat("aves", 1024), BMPM_ASHKENAZI);
?>
--EXPECTF--
(lYkYin|lYkoin|lakYin|lakoin|lokYin|lokoin)-((lYkYin|lYkoin|lakYin|lakoin|lokYin|lokoin)-((kYin|koin)-(dYlYkYin|dYlYkoin|dYlakYin|dYlakoin|dYlokYin|dYlokoin|dilYkYin|dilYkoin|dilakYin|dilakoin|dilokYin|dilokoin)))
bool(true)

Fatal error: bmpm(): phonetic: BMPM encode exceeds work budget in %s on line %d
