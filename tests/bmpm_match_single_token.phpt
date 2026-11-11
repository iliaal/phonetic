--TEST--
bmpm_match(): single-token intersections preserve boundaries and prefix groups
--EXTENSIONS--
phonetic
--FILE--
<?php
// Pin encodings so the cases exercise the intended token shapes, rather than
// silently changing paths when rule data changes. Distinct inputs avoid the
// identical-raw-string shortcut, and both orders exercise the shorter swap.
$codes = [
    'c' => 'k', 'k' => 'k', 'sh' => 'S', 'sk' => 'sk', 'sch' => 'S|sk',
    's' => 's', 'ks' => 'ks', 'Smith' => 'smit', 'Smits' => 'smits',
    'a' => 'a|e|o', 'e' => '|e', 'eh' => 'e', 'ee' => 'i',
    "d'" => '()-(t)', "d''" => '()-(()-(t))', "d'ch" => '(tS|x)-(dx|tS)',
];
foreach ($codes as $name => $expected) {
    if (bmpm($name, BMPM_GENERIC, BMPM_EXACT, 'english') !== $expected) {
        throw new Exception("Unexpected encoding: $name");
    }
}
$cases = [
    ['c', 'k', true],
    ['sh', 'sch', true],           // First probe token.
    ['sk', 'sch', true],           // Last probe token.
    ['eh', 'a', true],             // Middle probe token.
    ['s', 'sch', false],           // No partial-token matches.
    ['s', 'ks', false],
    ['sh', 's', false],            // Phonetic tokens are case-sensitive.
    ['Smith', 'Smits', false],
    ['e', 'eh', true],             // Empty alternative before the only token.
    ['e', 'ee', false],
    ["d'", "d''", true],          // Grouped singleton is the indexed side.
    ["d'", "d'ch", false],        // Grouped token must not match tS or dx.
];
foreach ($cases as [$a, $b, $expected]) {
    foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
        if (bmpm_match($left, $right, BMPM_GENERIC, BMPM_EXACT, 'english') !== $expected) {
            throw new Exception("Unexpected match: $left / $right");
        }
    }
}
echo "single-token matches: ok\n";
// Nonempty serialization with no tokens must remain a non-match.
foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
    foreach (['arabic', 'hebrew', 'greek'] as $language) {
        foreach (['van Smith', 'van van Smith'] as $name) {
            if (preg_match('/^[()|\-]+$/D', bmpm($name, BMPM_GENERIC, $accuracy, $language)) !== 1) {
                throw new Exception('Expected nonempty groups without tokens');
            }
        }
        if (bmpm_match('van Smith', 'van van Smith', BMPM_GENERIC, $accuracy, $language)) {
            throw new Exception('Empty groups must not match');
        }
    }
}
echo "empty-token groups: ok\n";
?>
--EXPECT--
single-token matches: ok
empty-token groups: ok
