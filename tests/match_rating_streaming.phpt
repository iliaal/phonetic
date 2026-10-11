--TEST--
match_rating(): streaming codex preserves truncation boundaries and pair removal
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    // One below, at, and one above the six-character codex boundary.
    ['BCDFG', 'BCDFG'],
    ['BCDFGH', 'BCDFGH'],
    ['BCDFGHJ', 'BCDGHJ'],
    // A pair must be removed before deciding which three tail bytes to keep.
    ['BCDFGHH', 'BCDFGH'],
    ['BCDFGHHH', 'BCDGHH'],
    ['BCDFGHHHH', 'BCDGHH'],
    ['BBB', 'BB'], // Non-overlapping pairs, not run deduplication.
    ['BBBB', 'BB'],
    ['B!B', 'B!B'], // Retained punctuation breaks a consonant pair.
    ["B\0B", "B\0B"], // So does a retained NUL.
    ['BCDFGHJae.-', 'BCDGHJ'], // Dropped tail characters do not shift the codex.
    ['BABA-B B', 'BB'], // Removed vowels and punctuation do not break pairs.
    ['AaeBCDFGHJ', 'ABCGHJ'], // Only the first cleaned vowel survives.
    ['11!!00??', '11!0??'], // Digits and punctuation are not paired consonants.
    ["BC\0DFGH", "BC\0FGH"], // NUL is retained, even in the fixed prefix.
    ['BßßC', 'BSSC'],
    ["B\xDF\xDFC", 'BSSC'], // Malformed UTF-8 Latin-1 fallback also expands.
];
foreach ($cases as [$input, $expected]) {
    if (match_rating($input) !== $expected) {
        echo 'mismatch: ', bin2hex($input), "\n";
    }
    // A trailing space bypasses the raw equality fast path in both orders.
    if (!match_rating_compare($input, $input . ' ')
            || !match_rating_compare($input . ' ', $input)) {
        echo 'comparison mismatch: ', bin2hex($input), "\n";
    }
}
echo "ok\n";
?>
--EXPECT--
ok
