--TEST--
dm_soundex(): mn/nm force rule and argument typing
--EXTENSIONS--
phonetic
--FILE--
<?php
// "mn" / "nm" are forced to code both digits even though 6 follows 6.
var_dump(dm_soundex("Mnemonic"));
var_dump(dm_soundex("Amnon"));

// Single required string argument.
try {
    dm_soundex();
} catch (\ArgumentCountError $e) {
    echo get_class($e), "\n";
}
try {
    dm_soundex([]);
} catch (\TypeError $e) {
    echo get_class($e), "\n";
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(6) "666640"
  [1]=>
  string(6) "666650"
}
array(1) {
  [0]=>
  string(6) "066600"
}
ArgumentCountError
TypeError
