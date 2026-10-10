--TEST--
dm_soundex(): stop only after every branch has six digits, preserving code order
--EXTENSIONS--
phonetic
--FILE--
<?php
// The first branch is full, but the second still needs the final r.
foreach (['bptkcjmn', 'bptkcjmnr'] as $name) {
    echo implode('|', dm_soundex($name)), "\n";
}

// Once all branches are full, even branching rules, forced mn/nm pairs,
// malformed UTF-8, and embedded NULs in the remaining input cannot change them.
foreach (['cjcbptkmn', 'Rosochowaciec'] as $name) {
    $suffix = str_repeat("cjęţmn\xff\0", 300);
    echo implode('|', dm_soundex($name)), "\n";
    var_dump(dm_soundex($name . $suffix) === dm_soundex($name));
    var_dump(dm_soundex_match($name, $name . $suffix));
    var_dump(dm_soundex_match($name . $suffix, $name));
    var_dump(dm_soundex_match($name . $suffix, 'A'));
}

// Finishing a codex early does not bypass the public input-length validation.
try {
    dm_soundex('cjcbptkmn' . str_repeat('a', 4096));
} catch (ValueError $e) {
    echo "input cap enforced\n";
}
?>
--EXPECT--
735466|735660
735466|735669
447356|457356|547356|557356|545735
bool(true)
bool(true)
bool(true)
bool(false)
944744|944745|944754|944755|945744|945745|945754|945755
bool(true)
bool(true)
bool(true)
bool(false)
input cap enforced
