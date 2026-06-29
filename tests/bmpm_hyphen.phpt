--TEST--
bmpm(): hyphen is treated as a word separator (matches Commons Codec)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Golden values from Apache Commons Codec 1.17.1. In the tidy step '-' becomes
// a space, so a hyphenated name encodes identically to the spaced form.
var_dump(bmpm("Jean-Pierre") === bmpm("Jean Pierre"));
var_dump(bmpm("Anne-Marie")  === bmpm("Anne Marie"));
echo bmpm("Smith-Jones"), "\n";
?>
--EXPECT--
bool(true)
bool(true)
zmQtionis|zmidzoni|zmidzoniS|zmidzonis|zmitioni|zmitioniS|zmitionis|zmitiunis|zmitxonis|zmizonis
