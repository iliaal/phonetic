--TEST--
Double Metaphone truncates multi-character emissions independently for encoding and matching
--EXTENSIONS--
phonetic
--FILE--
<?php
// Literal full keys pin the X, GN and WICZ branches, including unequal key
// lengths. Limits cut both two-character emissions and just one of the keys.
$codes = [
    'AX' => ['AKS', 'AKS'],
    'JAX' => ['JKS', 'AKS'],
    'AGN' => ['AKN', 'AN'],
    'BAGN' => ['PN', 'PKN'],
    // Keep encoding until the shorter key also reaches the limit.
    'AGNB' => ['AKNP', 'ANP'],
    'BAGNB' => ['PNP', 'PKNP'],
    'AWICZ' => ['ATS', 'AFX'],
];
foreach ($codes as $word => [$primary, $alternate]) {
    foreach ([PHP_INT_MIN, -1, 0, 1, 2, 3, 4, PHP_INT_MAX] as $limit) {
        $expected = [
            'primary' => $limit > 0 ? substr($primary, 0, $limit) : $primary,
            'alternate' => $limit > 0 ? substr($alternate, 0, $limit) : $alternate,
        ];
        if (double_metaphone($word, $limit) !== $expected) {
            echo "encoding mismatch: $word / $limit\n";
        }
    }
}
// Distinct raw strings avoid the identical-input shortcut. The first three
// ratings are for limits 1, 2 and 3; the last is for every unlimited/large limit.
$pairs = [
    ['AX', 'AK', [2, 2, 0, 0]],
    ['JAX', 'AK', [1, 1, 0, 0]],
    ['AGN', 'AN', [2, 1, 1, 1]],
    ['BAGN', 'BK', [2, 1, 0, 0]],
    ['AWICZ', 'AF', [2, 1, 0, 0]],
];
foreach ($pairs as [$a, $b, $ratings]) {
    foreach ([1, 2, 3, 0, -1, PHP_INT_MIN, PHP_INT_MAX] as $index => $limit) {
        $expected = $ratings[min($index, 3)];
        foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
            if (double_metaphone_match($left, $right, $limit) !== $expected) {
                echo "match mismatch: $left / $right / $limit\n";
            }
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
