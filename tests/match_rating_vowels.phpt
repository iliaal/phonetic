--TEST--
match_rating(): preserve the first cleaned character while removing later vowels
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    ['.,-', ''],
    ['.a', 'A'],
    ['.b', 'B'],
    ['AEIOUaeiou', 'A'],
    ['BAEIOUaeiou', 'B'],
    ['.,- Ébabc', 'EBC'],
    ['abbab', 'ABB'],
    ['bbab', 'BB'],
    ["a\0eB", "A\0B"],
    ['a123aeiou', 'A123'],
    ['a' . str_repeat('be', 20000) . 'cd', 'ABBBCD'],
    ['b' . str_repeat('aeiou', 20000), 'B'],
];
foreach ($cases as $index => [$input, $expected]) {
    $actual = match_rating($input);
    if ($actual !== $expected) {
        echo "case $index failed: ", bin2hex($actual), "\n";
    }
}
// Distinct raw inputs exercise both encodes rather than the equality shortcut.
var_dump(match_rating_compare('aeiou', 'aaaa'));
var_dump(match_rating_compare('abbab', 'abbeb'));
echo "done\n";
?>
--EXPECT--
bool(true)
bool(true)
done
