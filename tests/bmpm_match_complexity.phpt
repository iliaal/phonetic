--TEST--
bmpm_match(): each operand gets a full per-encode allowance; one pathological operand still fails hard
--EXTENSIONS--
phonetic
--FILE--
<?php
$sa = str_repeat("de ", 50) . "cohen";
$sb = str_repeat("de ", 50) . "baker";
var_dump(bmpm_match($sa, $sb, BMPM_GENERIC, BMPM_APPROX, "english"));   // disjoint -> false

// Force English to skip language guesses; each operand fits the 32 MiB copy cap.
$a = str_repeat("de ", 380) . "cohen";
$b = str_repeat("de ", 380) . "baker";
var_dump(is_string(bmpm($a, BMPM_GENERIC, BMPM_APPROX, "english")));
var_dump(is_string(bmpm($b, BMPM_GENERIC, BMPM_APPROX, "english")));

// The two operands must receive independent budgets.
var_dump(bmpm_match($a, $b, BMPM_GENERIC, BMPM_APPROX, "english"));

// One over-budget operand must still fail.
$sep = str_repeat("-", 6);
bmpm_match(str_repeat("de" . $sep, 6) . str_repeat("aves", 300), "cohen", BMPM_GENERIC, BMPM_APPROX, "english");
?>
--EXPECTF--
bool(false)
bool(true)
bool(true)
bool(false)

Fatal error: bmpm_match(): phonetic: BMPM encode exceeds work budget in %s on line %d
