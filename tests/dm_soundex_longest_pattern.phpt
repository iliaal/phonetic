--TEST--
dm_soundex(): longest overlapping patterns retain their codes and byte consumption
--EXTENSIONS--
phonetic
--FILE--
<?php
// Longer clusters must win even when a shorter rule precedes them in the
// table. The following consonant also checks how many input bytes were used.
$cases = [
    'schtschab' => ['270000'],
    'schtshab' => ['270000'],
    'schtchab' => ['270000'],
    'stschab' => ['270000'],
    'tschab' => ['470000'],
    'schab' => ['470000'],
    'scha' => ['400000'],
    'sch' => ['400000'],
    // Non-ASCII rules and invalid UTF-8's Latin-1 fallback must still advance
    // by the matched pattern's byte length, not its code-point length.
    'ţab' => ['370000', '470000'],
    'țab' => ['370000', '470000'],
    'ęab' => ['700000'],
    'ąab' => ['700000'],
    'aţab' => ['037000', '047000'],
    'ațab' => ['037000', '047000'],
    "\xFEţab" => ['737000', '747000'],
];
foreach ($cases as $word => $expected) {
    if (dm_soundex($word) !== $expected) {
        echo 'unexpected codes for ', bin2hex($word), "\n";
    }
    // The helper shares the same rule-selection path.
    if (!dm_soundex_match($word, $word . ' ')) {
        echo 'unexpected mismatch for ', bin2hex($word), "\n";
    }
}
echo "longest patterns preserved\n";
?>
--EXPECT--
longest patterns preserved
