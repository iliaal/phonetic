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
?>
--EXPECT--
ionzmit
zmQtionis|zmidzoni|zmidzoniS|zmidzonis|zmitioni|zmitioniS|zmitionis|zmitiunis|zmitxonis|zmizonis
mDlir|mlYr|mlir|mvilr|mvlYr|mvli|mvlir
