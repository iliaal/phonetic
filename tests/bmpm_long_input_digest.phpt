--TEST--
bmpm(), bmpm_match(): multi-word inputs and match pairs match the digest recorded before the final-pass overhead changes
--EXTENSIONS--
phonetic
--FILE--
<?php
// Multi-word inputs reach the final-pass paths that short names rarely do:
// heap-allocated phoneme texts, hashed merging, and sets of more than 32
// phonemes. The digest was recorded from the build before phonemes were
// extended in place; it covers every name type and accuracy (budget errors
// included) plus bmpm_match() on random corpus pairs.
require __DIR__ . '/bmpm_corpus.inc';
$short = bmpm_corpus(2000, 20261119);
mt_srand(20261119);
$long = [];
for ($i = 0; $i < 300; $i++) {
    $w = [];
    $nw = mt_rand(1, 4);
    for ($k = 0; $k < $nw; $k++) {
        $w[] = $short[mt_rand(0, count($short) - 1)] . $short[mt_rand(0, count($short) - 1)];
    }
    $long[] = implode(mt_rand(0, 1) ? ' ' : '-', $w);
}
$h = hash_init('sha256');
foreach ([BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC] as $nt) {
    foreach ([BMPM_APPROX, BMPM_EXACT] as $acc) {
        foreach ($long as $s) {
            try {
                $r = bmpm($s, $nt, $acc);
            } catch (\Throwable $e) {
                $r = get_class($e) . ':' . $e->getMessage();
            }
            hash_update($h, "$nt/$acc/$s=$r\n");
        }
        for ($i = 0; $i < 500; $i++) {
            $a = $short[mt_rand(0, count($short) - 1)];
            $b = $short[mt_rand(0, count($short) - 1)];
            try {
                $r = bmpm_match($a, $b, $nt, $acc) ? '1' : '0';
            } catch (\Throwable $e) {
                $r = get_class($e) . ':' . $e->getMessage();
            }
            hash_update($h, "m$nt/$acc/$a/$b=$r\n");
        }
    }
}
echo hash_final($h), "\n";
?>
--EXPECT--
783b0d08ed495cc138eb532f8fea85c677feba43036b970c16bf7a198ba17ef9
