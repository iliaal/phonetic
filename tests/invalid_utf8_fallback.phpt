--TEST--
Malformed UTF-8 name bytes follow each encoder's documented input policy
--EXTENSIONS--
phonetic
--FILE--
<?php
// Explicit UTF-8 equivalents of byte-wise Latin-1 fallback. The resync case
// keeps the valid e-acute sequence after consuming only the invalid E0 byte.
$cases = [
    'continuation' => ["\x80", "\u{80}"],
    'truncated' => ["\xC3", "\u{C3}"],
    'resync' => ["\xE0\xC3\xA9", "\u{E0}\u{E9}"],
    'overlong-2' => ["\xC0\xAF", "\u{C0}\u{AF}"],
    'overlong-3' => ["\xE0\x80\x80", "\u{E0}\u{80}\u{80}"],
    'overlong-4' => ["\xF0\x80\x80\x80", "\u{F0}\u{80}\u{80}\u{80}"],
    'surrogate' => ["\xED\xA0\x80", "\u{ED}\u{A0}\u{80}"],
    'out-of-range' => ["\xF4\x90\x80\x80", "\u{F4}\u{90}\u{80}\u{80}"],
    'invalid-lead' => ["\xFF", "\u{FF}"],
];
$algorithms = [
    ['double_metaphone', 'double_metaphone_match', []],
    ['dm_soundex', 'dm_soundex_match', []],
    ['match_rating', 'match_rating_compare', []],
    ['nysiis', 'nysiis_match', []],
];
foreach ([BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC] as $type) {
    foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
        $algorithms[] = ['bmpm', 'bmpm_match', [$type, $accuracy]];
    }
}
$algorithms[] = ['bmpm', 'bmpm_match', [BMPM_GENERIC, BMPM_APPROX, 'english']];
$checks = 0;
foreach ($cases as $label => [$raw, $fallback]) {
    foreach ([['Bert', ''], ['', 'Bert']] as [$prefix, $suffix]) {
        foreach ($algorithms as [$encode, $match, $args]) {
            $case = $label . ($prefix === '' ? '/before' : '/after') . json_encode($args);
            $input = $prefix . $raw . $suffix;
            // NYSIIS drops every non-ASCII byte instead of decoding it.
            $equivalent = $prefix . ($encode === 'nysiis' ? '' : $fallback) . $suffix;
            $expected = $encode($equivalent, ...$args);
            $actual = $encode($input, ...$args);
            if ($actual !== $expected) {
                echo "$case/$encode: encoding differs\n";
            }
            foreach ([[$input, $equivalent], [$equivalent, $input]] as [$a, $b]) {
                if ($match($a, $b, ...$args) !== $match($equivalent, $equivalent, ...$args)) {
                    echo "$case/$match: matching differs\n";
                }
                $checks++;
            }
            $checks++;
        }
    }
}
// The comparison helper retains its documented pre-cleaning raw-equality
// shortcut, after rejecting inputs with at most one decoded code point.
var_dump(match_rating("\x80\x81"));
var_dump(match_rating_compare("\x80\x81", "\x80\x81"));
var_dump(match_rating_compare("\x80", "\x80"));
echo "$checks checks\n";
?>
--EXPECT--
string(0) ""
bool(true)
bool(false)
594 checks
