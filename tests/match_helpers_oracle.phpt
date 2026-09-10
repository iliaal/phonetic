--TEST--
match helpers: small oracle pins (encode gate cannot see these)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Encoder parity does not exercise comparison contracts.

var_dump(double_metaphone_match("Catherine", "Kathryn"));
var_dump(double_metaphone_match("Vagner", "Wagner"));
var_dump(double_metaphone_match("Smith", "Xylophone"));

var_dump(bmpm_match("Jackson", "Jaxon"));
var_dump(bmpm_match("Smith", "Xylophone"));
var_dump(dm_soundex_match("Moskowitz", "Moskovitz"));
var_dump(dm_soundex_match("Peterson", "Peters"));
var_dump(nysiis_match("Smith", "Schmit"));
var_dump(nysiis_match("Smith", "Xylophone"));
var_dump(match_rating_compare("Catherine", "Kathryn"));
var_dump(match_rating_compare("Smith", "Xylophone"));

// Commons Codec 1.17.1 isEncodeEquals: unequal lengths near the rating boundary.
var_dump(match_rating_compare("CATHRINE", "CATHERI"));
var_dump(match_rating_compare("MARTINEZ", "MARTIN"));
var_dump(match_rating_compare("AXBRIDGE", "AXEBRIDG"));
// Same-length near-miss just under the bar: CTHRN vs THMSN sums to 10
// (min rating 3) with rating 2, so Commons Codec 1.17.1 isEncodeEquals is false.
var_dump(match_rating_compare("Catherine", "Thomson"));
var_dump(match_rating_compare("&A", "&B"));

// Unlike Commons Codec's primary-only helper, alternate codes also cross:
// ja=J/A and aei=A/A, in either argument order.
var_dump(double_metaphone_match("ja", "aei"));
var_dump(double_metaphone_match("aei", "ja"));

var_dump(double_metaphone_match("", ""));
var_dump(bmpm_match("", ""));
var_dump(dm_soundex_match("", ""));
var_dump(nysiis_match("", ""));
var_dump(match_rating_compare("", ""));
?>
--EXPECT--
int(2)
int(1)
int(0)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
int(1)
int(1)
int(0)
bool(false)
bool(false)
bool(false)
bool(false)
