--TEST--
nysiis(): bounded transcoding preserves final cleanup and full-length encodings
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    'BAYS' => 'BY',
    'BCAYS' => 'BCY',
    'BCDFAYS' => 'BCDFY',
    'BCDFGAYS' => 'BCDFGY',
    'BCDFGHAYS' => 'BCDFGY',
    'BCDFGAS' => 'BCDFG',
    'BCDFGEE' => 'BCDFGY',
    'BCDFGNT' => 'BCDFGD',
    'AS' => '',
    'A' => 'A',
    "b\0c-d\xfffGays" => 'BCDFGY',
    'B' . str_repeat('AC', 5000) => 'B' . str_repeat('AC', 5000),
    str_repeat('B', 10000) . 'AYS' => 'BY',
];
foreach ($cases as $input => $full) {
    foreach ([PHP_INT_MIN, -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, PHP_INT_MAX] as $limit) {
        $expected = $limit > 0 ? substr($full, 0, $limit) : $full;
        if (nysiis($input, $limit) !== $expected) {
            echo "encode mismatch: ", bin2hex(substr($input, 0, 20)), " / $limit\n";
        }
        $other = strtolower($input) . '-';
        foreach ([[$input, $other], [$other, $input]] as [$a, $b]) {
            if (nysiis_match($a, $b, $limit) !== ($expected !== '')) {
                echo "match mismatch\n";
            }
        }
    }
}
// Equal bounded keys may differ when unlimited; the suffix still matters then.
$a = 'BCDFGLP';
$b = $a . 'R';
var_dump(nysiis_match($a, $b), nysiis_match($b, $a));
var_dump(nysiis_match($a, $b, 0), nysiis_match($b, $a, -1));
echo "ok\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
ok
