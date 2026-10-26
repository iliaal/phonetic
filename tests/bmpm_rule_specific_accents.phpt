--TEST--
bmpm(): lowercasing preserves language-specific accent and ligature handling
--EXTENSIONS--
phonetic
--FILE--
<?php
// README's forced-language example: case is folded, but ü is not stripped.
foreach (["Müller", "MÜLLER", "Muller"] as $name) {
    echo bmpm($name, BMPM_GENERIC, BMPM_EXACT, "german"), "\n";
}
var_dump(bmpm_match("Müller", "Muller", BMPM_GENERIC, BMPM_EXACT, "german"));
// No general æ -> ae expansion: the forced German rules leave æ unencodable.
foreach (["æ", "Æ", "ae"] as $name) {
    var_dump(bmpm($name, BMPM_GENERIC, BMPM_EXACT, "german"));
}
?>
--EXPECT--
mQler
mQler
muler
bool(false)
string(0) ""
string(0) ""
string(1) "Y"
