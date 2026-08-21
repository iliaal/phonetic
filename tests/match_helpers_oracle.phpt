--TEST--
match helpers: small oracle pins (encode gate cannot see these)
--EXTENSIONS--
phonetic
--FILE--
<?php
// Complement to parity_golden.phpt (encoders only): pin comparison contracts
// that would stay green if every *_match always returned true/false/2.

// Double Metaphone strength ladder
var_dump(double_metaphone_match("Catherine", "Kathryn")); // 2
var_dump(double_metaphone_match("Vagner", "Wagner"));     // 1
var_dump(double_metaphone_match("Smith", "Xylophone"));   // 0

// BMPM / DM / NYSIIS / MRA true+false pairs
var_dump(bmpm_match("Jackson", "Jaxon"));
var_dump(bmpm_match("Smith", "Xylophone"));
var_dump(dm_soundex_match("Moskowitz", "Moskovitz"));
var_dump(dm_soundex_match("Peterson", "Peters"));
var_dump(nysiis_match("Smith", "Schmit"));
var_dump(nysiis_match("Smith", "Xylophone"));
var_dump(match_rating_compare("Catherine", "Kathryn"));
var_dump(match_rating_compare("Smith", "Xylophone"));

// MRA: unequal-codex-length pairs near the min-rating boundary, and the
// raw-vs-clean trivial guard. All pins verified against Commons Codec 1.17.1
// isEncodeEquals (MatchRatingApproachEncoder).
var_dump(match_rating_compare("CATHRINE", "CATHERI"));
var_dump(match_rating_compare("MARTINEZ", "MARTIN"));
var_dump(match_rating_compare("AXBRIDGE", "AXEBRIDG"));
var_dump(match_rating_compare("&A", "&B"));

// Double Metaphone crossing superset vs the oracle helper: Commons Codec's
// isDoubleMetaphoneEqual compares primaries only and returns false here; our
// strength ladder deliberately also crosses alternates (AGENTS.md level 1:
// "ja"=J/A vs "aei"=A/A). Pinned in both argument orders.
var_dump(double_metaphone_match("ja", "aei"));
var_dump(double_metaphone_match("aei", "ja"));

// Empty never matches (including empty↔empty)
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
bool(true)
int(1)
int(1)
int(0)
bool(false)
bool(false)
bool(false)
bool(false)
