--TEST--
bmpm(): multi-word and hyphenated names
--EXTENSIONS--
phonetic
--FILE--
<?php
echo bmpm("Smith-Jones"), "\n";
echo bmpm("Mueller"), "\n";
?>
--EXPECT--
zmQtionis|zmidzoni|zmidzoniS|zmidzonis|zmitioni|zmitioniS|zmitionis|zmitiunis|zmitxonis|zmizonis
mDlir|mlYr|mlir|mvilr|mvlYr|mvli|mvlir
