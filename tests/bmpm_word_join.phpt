--TEST--
bmpm(): word filtering preserves apostrophe suffixes and empty joined segments
--EXTENSIONS--
phonetic
--FILE--
<?php
// Commons Codec 1.17.1 values. Empty apostrophe suffixes still contribute
// separators: trimming them changes anchored rules at the start/end of a name.
foreach (["e x'", "perez x'", "x' smith", "perez x' y'", "x' y' perez",
          "foo'de smith", "foo'bar smith", "a'b'cohen"] as $word) {
    echo bmpm($word, BMPM_SEPHARDIC, BMPM_EXACT), "\n";
}
echo bmpm("perez x'", BMPM_SEPHARDIC, BMPM_APPROX), "\n";

// Force a language so comparisons isolate word processing from language
// guessing, which intentionally happens before trimming and prefix removal.
$cases = [
    [BMPM_GENERIC, 'english', "bar\tben\nsmith\v\f\rde\tjones", 'bar ben smith de jones'],
    [BMPM_ASHKENAZI, 'russian', "bar\tben\nsmith\v\f\rde\tjones", 'smith jones'],
    [BMPM_SEPHARDIC, 'spanish', "bar\tben\nsmith\v\f\rde\tjones", 'bar ben smith jones'],
    [BMPM_ASHKENAZI, 'russian', 'bar ben de van von', ''],
    [BMPM_SEPHARDIC, 'spanish', 'de del van von', ''],
    [BMPM_SEPHARDIC, 'spanish', "x'de y'del z'van", ''],
    [BMPM_SEPHARDIC, 'spanish', "x' y'", ''],
    [BMPM_ASHKENAZI, 'russian', str_repeat('de ', 1365), ''],
];
foreach ($cases as [$nt, $language, $input, $joined]) {
    foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
        if (bmpm($input, $nt, $accuracy, $language) !== bmpm($joined, $nt, $accuracy, $language)) {
            echo "word filtering mismatch\n";
        }
    }
}
foreach ([BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC] as $nt) {
    foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
        foreach (['', " \t\r\n\v\f "] as $input) {
            if (bmpm($input, $nt, $accuracy) !== '') {
                echo "empty input mismatch\n";
            }
        }
    }
}
echo "word joins preserved\n";
?>
--EXPECT--
e
pereds|peres|perets
Zmit|zmit
pereds|peres|perets
pereS|peres|perets
smit
barZmit|barzmit|varzmit
koen
biriS|biridS|birids|biris|birits
word joins preserved
