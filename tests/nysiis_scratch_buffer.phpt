--TEST--
NYSIIS preserves keys and matches across the stack/heap scratch-buffer boundary
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [['', ''], ['MACdonald', 'MCDANALD']];
foreach ([1, 2, 125, 126, 127, 128, 129, 130, 255, 256, 4096] as $length) {
    // Alternating B/C survives every transcode and final-cleanup rule.
    $letters = substr(str_repeat('BC', (int) ceil($length / 2)), 0, $length);
    $cases[] = [$letters, $letters];
    $cases[] = [str_repeat('!', $length), ''];
    if ($length >= 3) {
        $prefix = substr($letters, 0, -3);
        $cases[] = [$prefix . 'AYS', $prefix . 'Y'];
    }
    // Size the scratch buffer by raw bytes, not by the short cleaned result.
    $cases[] = ['B' . str_repeat("\0\xff-", $length) . 'C', 'BC'];
}
// In-place replacement writes must fit even at the last bytes of each buffer.
foreach ([126, 127, 128, 129] as $length) {
    foreach (['EV' => 'AF', 'KN' => 'N', 'PH' => 'F', 'SCH' => ''] as $suffix => $tail) {
        $prefix = substr(str_repeat('BC', $length), 0, $length - strlen($suffix));
        $cases[] = [$prefix . $suffix, $prefix . $tail];
    }
}
foreach ($cases as [$input, $full]) {
    foreach ([PHP_INT_MIN, -1, 0, 1, 2, 6, 126, 127, 128, 129, PHP_INT_MAX] as $limit) {
        $expected = $limit > 0 ? substr($full, 0, $limit) : $full;
        if (nysiis($input, $limit) !== $expected) {
            echo 'encode mismatch: ', strlen($input), " / $limit\n";
        }
        $other = strtolower($input) . '-';
        foreach ([[$input, $input], [$input, $other], [$other, $input]] as [$a, $b]) {
            if (nysiis_match($a, $b, $limit) !== ($expected !== '')) {
                echo 'matching-key mismatch: ', strlen($input), " / $limit\n";
            }
        }
        foreach ([[$input, 'D'], ['D', $input]] as [$a, $b]) {
            if (nysiis_match($a, $b, $limit)) {
                echo 'distinct-key mismatch: ', strlen($input), " / $limit\n";
            }
        }
    }
}
echo "ok\n";
?>
--EXPECT--
ok
