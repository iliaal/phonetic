--TEST--
dm_soundex(): longest-first rule buckets fall back past oversized and nonmatching patterns
--EXTENSIONS--
phonetic
--FILE--
<?php
// Apache Commons Codec 1.17.1 DaitchMokotoffSoundex oracle results.
// Truncated clusters must keep looking for shorter matches. Same-length
// nonmatches must not hide a later match, including in multibyte buckets.
$cases = [
    's' => ['400000'],
    'sc' => ['200000'],
    'sch' => ['400000'],
    'scht' => ['200000'],
    'schts' => ['240000'],
    'schtsc' => ['240000'],
    'schtsch' => ['200000'],
    'sht' => ['200000'],
    'st' => ['200000'],
    'strz' => ['200000'],
    'szt' => ['200000'],
    'szcz' => ['200000'],
    'c' => ['400000', '500000'],
    'ch' => ['400000', '500000'],
    'ck' => ['500000', '450000'],
    'cz' => ['400000'],
    'cs' => ['400000'],
    'css' => ['400000'],
    'czs' => ['400000'],
    't' => ['300000'],
    'ts' => ['400000'],
    'ttsch' => ['400000'],
    'tch' => ['400000'],
    'ţ' => ['300000', '400000'],
    'ț' => ['300000', '400000'],
    'ę' => ['000000'],
    'ą' => ['000000'],
    'x' => ['500000'],
];
foreach ($cases as $word => $expected) {
    if (dm_soundex($word) !== $expected) {
        echo 'unexpected codes: ', bin2hex($word), "\n";
    }
    // Distinct raw inputs force both operands through the shared lookup.
    foreach ([[$word, $word . ' '], [$word . ' ', $word]] as [$a, $b]) {
        if (!dm_soundex_match($a, $b)) {
            echo 'unexpected mismatch: ', bin2hex($word), "\n";
        }
        if (dm_soundex_match($a, 'l') || dm_soundex_match('l', $b)) {
            echo 'unexpected match: ', bin2hex($word), "\n";
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
