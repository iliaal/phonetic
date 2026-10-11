--TEST--
Match Rating encodes and compares large inputs without input-sized temporary buffers
--EXTENSIONS--
phonetic
--INI--
memory_limit=16M
--FILE--
<?php
// The 6 MiB input fits in the limit; an additional 12 MiB cleaning buffer does not.
$input = str_repeat('BC', 3 * 1024 * 1024);
var_dump(match_rating($input));
var_dump(match_rating_compare($input, 'BCBCBC'));
var_dump(match_rating_compare('BCBCBC', $input));
unset($input);
// Bare Latin-1 sharp S expands during cleaning and then collapses in pairs.
$input = str_repeat("\xDF", 6 * 1024 * 1024);
var_dump(match_rating($input));
var_dump(match_rating_compare($input, 'SSSSSSSSSSSS'));
unset($input);
$input = str_repeat('. ', 3 * 1024 * 1024);
var_dump(match_rating($input));
var_dump(match_rating_compare($input, 'Smith'));
var_dump(match_rating_compare('Smith', $input));
?>
--EXPECT--
string(6) "BCBCBC"
bool(true)
bool(true)
string(6) "SSSSSS"
bool(true)
string(0) ""
bool(false)
bool(false)
