--TEST--
dm_soundex(): a per-operand work budget bounds branching cycles and throws without partial results
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach (['jęh', 'ęąj', 'jęę', 'jęj'] as $unit) {
    $input = substr('cc' . str_repeat($unit, 4096), 0, 4096);
    $errors = $finalizers = 0;
    foreach ([
        fn() => dm_soundex($input),
        fn() => dm_soundex_match($input, 'Katz'),
        fn() => dm_soundex_match('Katz', $input),
        fn() => dm_soundex_match($input, $input),
    ] as $call) {
        try {
            $call();
            echo "unexpected result\n";
        } catch (ValueError $e) {
            if ($e->getMessage() !== 'phonetic: DM Soundex encode exceeds work budget') {
                echo $e->getMessage(), "\n";
            }
            $errors++;
        } finally {
            $finalizers++;
        }
    }
    echo $unit, ': ', $errors, ' errors, ', $finalizers, " finalizers\n";
}

// Each operand can independently consume most of the work allowance.
$a = 'cc' . str_repeat('jęh', 48);
$b = str_replace('ę', 'ą', $a);
$codes = dm_soundex($a);
echo count($codes), ' ', hash('sha256', implode('|', $codes)), "\n";
var_dump(dm_soundex_match($a, $b));
var_dump(dm_soundex_match($b, $a));
echo implode('|', dm_soundex('Katz')), "\n";
var_dump(dm_soundex_match('Moskowitz', 'Moskovitz'));
?>
--EXPECT--
jęh: 4 errors, 4 finalizers
ęąj: 4 errors, 4 finalizers
jęę: 4 errors, 4 finalizers
jęj: 4 errors, 4 finalizers
128 413789a8e50d42fe9ded38cbab2231ff3fb5fab6d7958ef1ea8b2596acdd68aa
bool(true)
bool(true)
540000
bool(true)
