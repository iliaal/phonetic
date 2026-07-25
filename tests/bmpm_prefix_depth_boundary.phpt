--TEST--
bmpm(): prefix recursion cap (6) boundary — group count stops growing at the cap
--EXTENSIONS--
phonetic
--FILE--
<?php
// Each "de " prefix adds one (...)-(...) branch until BMPM_MAX_PREFIX_DEPTH
// (6) is reached; an off-by-one in the depth comparison changes these.
foreach ([5, 6, 7] as $n) {
    $out = bmpm(str_repeat("de ", $n) . "cohen");
    printf("N=%d groups=%d strlen=%d\n", $n, substr_count($out, ")-(") + 1, strlen($out));
}
?>
--EXPECT--
N=5 groups=6 strlen=865
N=6 groups=7 strlen=1209
N=7 groups=7 strlen=1579
