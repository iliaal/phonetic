--TEST--
bmpm_match(): each operand gets a full per-encode allowance; one pathological operand still fails hard
--EXTENSIONS--
phonetic
--FILE--
<?php
// Disjoint prefix-amplified inputs must not false-match; a moderate
// amplification exercises the intersection logic and stays within budget.
$sa = str_repeat("de ", 50) . "cohen";
$sb = str_repeat("de ", 50) . "baker";
var_dump(bmpm_match($sa, $sb, BMPM_GENERIC, BMPM_APPROX, "english"));   // disjoint -> false

// A forced language skips language guessing, so the only budget these larger
// operands can cross is the phoneme-text WORK dimension. Each encodes fine on
// its own -- individually within the 32 MiB work cap.
$a = str_repeat("de ", 380) . "cohen";
$b = str_repeat("de ", 380) . "baker";
var_dump(is_string(bmpm($a, BMPM_GENERIC, BMPM_APPROX, "english")));
var_dump(is_string(bmpm($b, BMPM_GENERIC, BMPM_APPROX, "english")));

// Each operand gets a full per-encode allowance, so this pair -- each within
// the work cap alone -- returns instead of failing hard (disjoint -> false).
var_dump(bmpm_match($a, $b, BMPM_GENERIC, BMPM_APPROX, "english"));

// But a SINGLE pathological operand still fails hard against its own
// allowance. Last statement: the fatal halts execution.
$sep = str_repeat("-", 6);
bmpm_match(str_repeat("de" . $sep, 6) . str_repeat("aves", 300), "cohen", BMPM_GENERIC, BMPM_APPROX, "english");
?>
--EXPECTF--
bool(false)
bool(true)
bool(true)
bool(false)

Fatal error: bmpm_match(): phonetic: BMPM encode exceeds work budget in %s on line %d
