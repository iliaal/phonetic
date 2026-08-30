--TEST--
bmpm_match(): disjoint prefix-amplified inputs do not false-match; both operands share one work budget
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

// But bmpm_match() shares ONE budget across BOTH operand encodes, so this pair
// -- each operand within the work cap alone, the two together over it -- fails
// hard. A per-operand budget (or one that reset work/phonemes/bytes between
// operands) would wrongly allow it (CR-002 discriminator, work dimension). Last
// statement: the fatal halts execution.
bmpm_match($a, $b, BMPM_GENERIC, BMPM_APPROX, "english");
?>
--EXPECTF--
bool(false)
bool(true)
bool(true)

Fatal error: bmpm_match(): phonetic: BMPM encode exceeds work budget in %s on line %d
