--TEST--
double_metaphone_match(): match strength 2 (strong) / 1 (weak) / 0 (none)
--EXTENSIONS--
phonetic
--FILE--
<?php
// 2 = primary codes agree
var_dump(double_metaphone_match("Smith", "Smith"));
var_dump(double_metaphone_match("Stephen", "Stefan"));
var_dump(double_metaphone_match("Catherine", "Kathryn"));
// bare "Jose" takes the Spanish-H primary (HS), so this pair is strong too
var_dump(double_metaphone_match("Jose", "Hose"));
// 1 = primaries differ but an alternate code crosses
var_dump(double_metaphone_match("Vagner", "Wagner"));
// 0 = no shared code
var_dump(double_metaphone_match("Smith", "Xylophone"));
var_dump(double_metaphone_match("", ""));
var_dump(double_metaphone_match("x", ""));
// max_length is honoured: full codes still match an identical word
var_dump(double_metaphone_match("Wisniewski", "Wisniewski", 0));
?>
--EXPECT--
int(2)
int(2)
int(2)
int(2)
int(1)
int(0)
int(0)
int(0)
int(2)
