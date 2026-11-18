--TEST--
BMPM preserves the combining dot from U+0130 lowercasing in rule contexts
--EXTENSIONS--
phonetic
--FILE--
<?php
// Apache Commons Codec 1.17.1 golden outputs, in name-type/accuracy order.
foreach ([BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC] as $type) {
    foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
        foreach (["Brİown", "İhsan"] as $name) {
            echo bmpm($name, $type, $accuracy), "\n";
            $lower = str_replace("İ", "i\u{0307}", $name);
            var_dump(bmpm($name, $type, $accuracy) === bmpm($lower, $type, $accuracy));
            var_dump(bmpm_match($name, $lower, $type, $accuracy));
            var_dump(bmpm_match($lower, $name, $type, $accuracy));
        }
    }
}
// Forced language and recursive prefix handling must see the expanded input.
echo bmpm("Brİown", BMPM_GENERIC, BMPM_EXACT, "english"), "\n";
echo bmpm("van Brİown", BMPM_GENERIC, BMPM_EXACT), "\n";
var_dump(bmpm("van Brİown", BMPM_GENERIC, BMPM_EXACT, "english") ===
    bmpm("van Bri\u{0307}own", BMPM_GENERIC, BMPM_EXACT, "english"));
// Expansion uses all 4096 code-point slots without changing the byte input cap.
var_dump(bmpm(str_repeat("İ", 2048), BMPM_GENERIC, BMPM_EXACT, "english") === str_repeat("i", 2048));
try {
    bmpm(str_repeat("İ", 2048) . "a");
    echo "accepted oversized input\n";
} catch (ValueError $e) {
    echo "oversized input rejected\n";
}
// Sibling encoders retain their own folding policies.
var_dump(dm_soundex("İzmir") === dm_soundex("Izmir"));
var_dump(double_metaphone("İhsan") === double_metaphone("hsan"));
var_dump(match_rating("İhsan") === match_rating("hsan"));
?>
--EXPECT--
brQYvn|brQovn|briYvn|briovn|briown|briuvn
bool(true)
bool(true)
bool(true)
Qsan|Qson|Qzan|Qzon|isan|ison|ixsan|ixson|izan|izon
bool(true)
bool(true)
bool(true)
briovn|briown
bool(true)
bool(true)
bool(true)
ihsan|ihzan|isan|ixsan
bool(true)
bool(true)
bool(true)
brQYvn|brQovn|briYvn|briovn|briuvn
bool(true)
bool(true)
bool(true)
Qsan|Qson|Qzan|Qzon|isan|ison|ixsan|ixson|izan|izon
bool(true)
bool(true)
bool(true)
briovn
bool(true)
bool(true)
bool(true)
ihsan|ihzan|ixsan
bool(true)
bool(true)
bool(true)
briuvn|vriuvn
bool(true)
bool(true)
bool(true)
ifsan|ihsan|isan
bool(true)
bool(true)
bool(true)
briovn|vriovn
bool(true)
bool(true)
bool(true)
iSan|isan
bool(true)
bool(true)
bool(true)
briavn|briawn|briovn|briown
(briovn|briown)-(fanbriovn|vanbriovn|vanbriown)
bool(true)
bool(true)
oversized input rejected
bool(true)
bool(true)
bool(true)
