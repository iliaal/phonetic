--TEST--
dm_soundex(): repeated branching stays near the cost of scanning equal-length input
--EXTENSIONS--
phonetic
--FILE--
<?php
function elapsed(callable $encode): int {
    $times = [];
    for ($round = 0; $round < 3; $round++) {
        $start = hrtime(true);
        for ($i = 0; $i < 5; $i++) {
            $encode();
        }
        $times[] = hrtime(true) - $start;
    }
    sort($times);
    return $times[1];
}

$input = 'cc' . str_repeat('ję', 1364) . 'ja';
$other = str_replace('ę', 'ą', $input);
$control = 'btkrlm' . str_repeat('ję', 1363) . 'a';
$controlOther = str_replace('ę', 'ą', $control);
echo count(dm_soundex($input)), "\n";
var_dump(dm_soundex_match($input, $other));

// The control incurs the same Unicode cleanup, then completes its code early.
// The relative bound accommodates slow builds; the 2 ms floor absorbs timer noise.
$scan = elapsed(fn() => dm_soundex($control));
$branch = elapsed(fn() => dm_soundex($input));
echo $branch < max(2000000, 20 * $scan) ? "encode bounded\n" : "encode too slow\n";
$scan = elapsed(fn() => dm_soundex_match($control, $controlOther));
$branch = elapsed(fn() => dm_soundex_match($input, $other));
echo $branch < max(2000000, 20 * $scan) ? "match bounded\n" : "match too slow\n";
?>
--EXPECT--
128
bool(true)
encode bounded
match bounded
