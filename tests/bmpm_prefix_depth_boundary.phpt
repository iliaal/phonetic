--TEST--
bmpm(): prefix recursion cap (16) boundary — group count stops growing at the cap
--EXTENSIONS--
phonetic
--FILE--
<?php
// Each "de " prefix adds one (...)-(...) branch until BMPM_MAX_PREFIX_DEPTH
// (16) is reached; an off-by-one in the depth comparison changes these.
foreach ([15, 16, 17] as $n) {
    $out = bmpm(str_repeat("de ", $n) . "cohen");
    printf("N=%d groups=%d strlen=%d\n", $n, substr_count($out, ")-(") + 1, strlen($out));
}
?>
--EXPECT--
N=15 groups=16 strlen=6105
N=16 groups=17 strlen=6849
N=17 groups=17 strlen=7619
