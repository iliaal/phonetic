--TEST--
match_rating_compare(): minimum-rating boundaries, length-gap rejection and symmetry
--EXTENSIONS--
phonetic
--FILE--
<?php
// Distinct consonants keep the codices unchanged and bypass raw equality.
// Each sum straddles a minimum-rating transition, with a match exactly at
// the required rating and a non-match one point below it.
$cases = [
    // Sum 4: minimum rating 5.
    ['BD', 'BF', true],
    ['BD', 'FG', false],
    // Sum 5: minimum rating drops to 4.
    ['BC', 'BDF', true],
    ['BC', 'DFG', false],
    // Sum 7: minimum rating is still 4.
    ['BCD', 'BCFG', true],
    ['BCD', 'BFGH', false],
    // Sum 8: minimum rating drops to 3.
    ['BCDF', 'BGHJ', true],
    ['BCDF', 'GHJK', false],
    // Sum 11: minimum rating is still 3.
    ['BCDGH', 'BCDJKL', true],
    ['BCDGH', 'BCFJKL', false],
    // Sum 12: minimum rating drops to 2.
    ['BCDFGH', 'BCJKLM', true],
    ['BCDFGH', 'BJKLMN', false],
    // A length gap of two is allowed. Three is rejected even though the
    // shared BCD prefix would otherwise meet the sum-9 minimum rating.
    ['BCD', 'BCDFG', true],
    ['BCD', 'BCDFGH', false],
];
foreach ($cases as [$a, $b, $expected]) {
    if (match_rating($a) !== $a || match_rating($b) !== $b) {
        echo "unexpected codex: $a / $b\n";
    }
    foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
        if (match_rating_compare($left, $right) !== $expected) {
            echo "comparison mismatch: $left / $right\n";
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
