--TEST--
double_metaphone(): seeded corpus digest across lengths, folds and code caps
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Seeded inputs mix clusters, Latin-1 folds (some expand to two letters),
 * stray UTF-8 bytes, separators and lengths past several buffer pages. The
 * digest was recorded from the smart_str implementation. */
$parts = ['SCH', 'CH', 'GH', 'TH', 'WR', 'KN', 'CZ', 'WICZ', 'MC', 'VAN ', 'A', 'E', 'I', 'O', 'U', 'Y',
    'B', 'C', 'D', 'G', 'J', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'V', 'W', 'X', 'Z', 'ch', 'sh',
    "\u{C6}", "\u{DE}", "\u{DF}", "\u{C7}", "\u{E9}", "\u{F1}", "\u{FE}", "\xDF", "\xC3", "\u{0142}",
    ' ', '-', "'", "\t", '1'];
$caps = [4, 1, 2, 3, 6, 0, -1, 200, 5000];
mt_srand(20261114);
$h = hash_init('sha256');
$sizes = [1, 2, 3, 5, 8, 13, 21, 40, 70, 130, 300, 1500, 6000];
$prev = '';
foreach ($sizes as $size) {
    for ($k = 0; $k < 120; $k++) {
        $w = '';
        $n = mt_rand(1, $size);
        for ($i = 0; $i < $n; $i++) {
            $w .= $parts[mt_rand(0, count($parts) - 1)];
        }
        foreach ($caps as $cap) {
            $c = double_metaphone($w, $cap);
            hash_update($h, $c['primary'] . '|' . $c['alternate'] . '|'
                . double_metaphone_match($w, $prev, $cap) . "\n");
        }
        $prev = $w;
    }
}
echo hash_final($h), "\n";
var_dump(array_keys(double_metaphone('Smith')));
?>
--EXPECT--
4c9fabe11dcd5687bcdd26bd78a9f976747ae5aa7712ca1eea4ad2ff9594611b
array(2) {
  [0]=>
  string(7) "primary"
  [1]=>
  string(9) "alternate"
}
