--TEST--
nysiis(): output compaction preserves transcode windows and trailing cleanup
--EXTENSIONS--
phonetic
--FILE--
<?php
// Cover adjacent output/read positions and gaps from collapsed duplicates.
$cases = [
    "AEVHWAEV" => "AFWAF",
    "BBBBEVHWPHKN" => "BAFWFN",
    "AEVPHWSCHKNH" => "AFWSN",
    "BACADAFAGAH" => "BACADAFAG",
    "AAASCHAHWA" => "AS",
    "AS" => "",
    "A" => "A",
    "AY" => "AY",
    "BAYS" => "BY",
    "BAS" => "B",
    "MACAEVPHKNW" => "MCAFNW",
    "a\0EV-\xffHwpH" => "AFWF",
    "é12\0" => "",
    str_repeat("B", 10000) . "EVPHKN" => "BAFN",
    "B" . str_repeat("AC", 5000) => "B" . str_repeat("AC", 5000),
];
foreach ($cases as $input => $expected) {
    foreach ([0, -1, 1, 2, 6] as $limit) {
        $want = $limit > 0 ? substr($expected, 0, $limit) : $expected;
        if (nysiis($input, $limit) !== $want) {
            echo "encode mismatch\n";
        }
        if (nysiis_match($input, strtolower($input), $limit) !== ($want !== '')) {
            echo "match mismatch\n";
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
