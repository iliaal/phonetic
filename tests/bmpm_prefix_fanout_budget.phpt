--TEST--
bmpm(): GENERIC prefix fan-out shares one work budget across all 2^depth leaves
--EXTENSIONS--
phonetic
--FILE--
<?php
// A small nested-prefix input still encodes normally (the depth cap and the
// budget leave real names untouched). Forcing a language skips per-child
// language guessing, isolating the phoneme-text WORK dimension.
var_dump(is_string(bmpm("van der berg", BMPM_GENERIC, BMPM_APPROX, "english")));

// The payload ALONE encodes well within the 32 MiB work budget: one leaf's
// worth of phoneme-text churn fits.
$payload = str_repeat("aves", 300);
var_dump(is_string(bmpm($payload, BMPM_GENERIC, BMPM_APPROX, "english")));

// Finding-1 shape: a prefix word followed by a >=2-char separator makes the
// combined child re-fire the SAME prefix, so 6 prefix words with 6-char
// separators build a complete binary tree of 2^6 = 64 leaf encodes, each
// amplifying the payload. Every leaf draws from ONE shared work budget, so the
// fan-out fails hard on the AGGREGATE churn even though a single leaf fits --
// proving the budget is shared across leaves, not reset per leaf (CWE-400). The
// URL-safe hyphen spelling contains no whitespace at the PHP-string level. Last
// statement: the fatal halts execution.
$sep = str_repeat("-", 6);
bmpm(str_repeat("de" . $sep, 6) . $payload, BMPM_GENERIC, BMPM_APPROX, "english");
?>
--EXPECTF--
bool(true)
bool(true)

Fatal error: bmpm(): phonetic: BMPM encode exceeds work budget in %s on line %d
