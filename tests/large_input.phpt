--TEST--
large inputs encode without pathological blow-up
--EXTENSIONS--
phonetic
--FILE--
<?php
// 40000-char name, full (untruncated) NYSIIS code.
var_dump(strlen(nysiis(str_repeat("ab", 20000), 0)));
// 20000-char name, untruncated Double Metaphone codes.
$code = double_metaphone(str_repeat("schm", 5000), 0);
var_dump(strlen($code['primary']), strlen($code['alternate']));
// MRA first3+last3 keeps the code at 6 no matter the input size.
var_dump(strlen(match_rating(str_repeat("br", 20000))));
try {
    bmpm(str_repeat("a", 20000));
} catch (\ValueError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(40000)
int(10000)
int(10000)
int(6)
bmpm(): Argument #1 ($string) must not exceed 4096 bytes
