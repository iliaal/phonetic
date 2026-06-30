--TEST--
match_rating_compare(): MRA homophone comparison (Commons Codec isEncodeEquals)
--EXTENSIONS--
phonetic
--FILE--
<?php
$expectTrue = [
    ["Brian", "Bryan"], ["Burns", "Bourne"], ["Catherine", "Kathryn"],
    ["Cólm.   ", "C-olín"], ["Sean", "John"], ["Franciszek", "Frances"],
    ["McGowan", "Mc Geoghegan"], ["Micky", "Michael"], ["Oona", "Oonagh"],
    ["Peterson", "Peters"], ["Sam", "Samuel"], ["Séan", "Shaun"],
    ["smith", "smyth"], ["Sophie", "Sofia"], ["Stephen", "Stefan"],
    ["Stephen", "Steven"], ["Steven", "Stefan"], ["Auerbach", "Uhrbach"],
    ["Cooper-Flynn", "Super-Lyn"], ["Hailey", "Halley"], ["LEWINSKY", "LEVINSKI"],
    ["LIPSHITZ", "LIPPSZYC"], ["Moskowitz", "Moskovitz"], ["Kl", "Karl"],
    ["Tomasz", "tom"], ["Zach", "Zacharia"], ["John", "John"],
    [" Brian ", "Bryan"], ["O'Sullivan", "Ó ' Súilleabháin"],
];
$expectFalse = [
    ["Sean", "Pete"], ["Úna", "Oonagh"], ["Karl", "Alessandro"],
    ["Al", "Ed"], ["Murphy", "Lynch"], ["Murphy", ""], ["Murphy", " "],
    ["Karl", "C"],
];
$fail = 0;
foreach ($expectTrue as [$a, $b]) {
    if (match_rating_compare($a, $b) !== true) { echo "want TRUE  [$a|$b]\n"; $fail++; }
}
foreach ($expectFalse as [$a, $b]) {
    if (match_rating_compare($a, $b) !== false) { echo "want FALSE [$a|$b]\n"; $fail++; }
}
echo $fail === 0 ? "all ok\n" : "$fail failures\n";
?>
--EXPECT--
all ok
