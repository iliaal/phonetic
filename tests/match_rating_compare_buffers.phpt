--TEST--
match_rating_compare(): short codex survives releasing the first input buffer
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    [str_repeat("BC", 5000), str_repeat("BC", 5000) . " ", true],
    [str_repeat("BC", 5000), str_repeat("XY", 5000), false],
    [str_repeat("BC", 5000), "Al", false], // Length-difference early return.
    [str_repeat(".,-", 5000), "Smith", false], // First codex is empty.
    ["Smith", str_repeat(".,-", 5000), false], // Second codex is empty.
    [str_repeat("é", 5000), "E ", true], // One-byte codex from multibyte input.
    [str_repeat("ß", 5000), str_repeat("S", 10000), true],
    [str_repeat("\xDF", 5000), str_repeat("S", 10000), true], // Latin-1 fallback expands.
    ["BC\0DFG", "BC\0DFG ", true], // Codex copy must retain embedded NUL.
    ["Smith", " smith ", true],
];
foreach ($cases as [$a, $b, $expected]) {
    foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
        if (match_rating_compare($left, $right) !== $expected) {
            echo "mismatch\n";
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
