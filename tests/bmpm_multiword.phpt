--TEST--
bmpm(): multi-word and hyphenated names
--EXTENSIONS--
phonetic
--FILE--
<?php
// Ordinary space-joined multiword (README example: concat, not prefix groups).
echo bmpm("John Smith"), "\n";
echo bmpm("Smith-Jones"), "\n";
echo bmpm("Mueller"), "\n";
// Match is token-set intersection on the concatenated encoding, not per-word:
// "John Smith" is one sequence (ionzmit), not a Smith-token that intersects Smith.
var_dump(bmpm_match("John Smith", "Smith"));
var_dump(bmpm_match("John Smith", "John Smith"));
?>
--EXPECT--
ionzmit
zmQtionis|zmidzoni|zmidzoniS|zmidzonis|zmitioni|zmitioniS|zmitionis|zmitiunis|zmitxonis|zmizonis
mDlir|mlYr|mlir|mvilr|mvlYr|mvli|mvlir
bool(false)
bool(true)
