--TEST--
dm_soundex(): whitespace stripping follows Java Character.isWhitespace exactly
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Commons Codec's DaitchMokotoffSoundex.cleanup() drops Character.isWhitespace
 * code points and keeps everything else. That set is exactly 25 code points; it
 * excludes the no-break spaces (U+00A0, U+2007, U+202F) *and* U+0085 NEXT LINE,
 * which Python and .NET do treat as whitespace. Expected values below come from
 * the pinned oracle (commons-codec 1.17.1). */
$cases = [
    // stripped: separator disappears, "s"+"ch" fuses into the sch digraph
    "s\tch"          => "400000",
    "s\nch"          => "400000",
    "s\x1cch"        => "400000",
    "s ch"           => "400000",
    "s\u{1680}ch"    => "400000",
    "s\u{2000}ch"    => "400000",
    "s\u{2028}ch"    => "400000",
    "s\u{205f}ch"    => "400000",
    "s\u{3000}ch"    => "400000",
    // NOT stripped: the separator survives cleaning and breaks the digraph
    "s\u{0085}ch"    => "400000|450000",
    "s\u{00a0}ch"    => "400000|450000",
    "s\u{2007}ch"    => "400000|450000",
    "s\u{202f}ch"    => "400000|450000",
    // reference: no separator at all
    "sch"            => "400000",
];
foreach ($cases as $in => $expected) {
    $got = implode("|", dm_soundex($in));
    printf("%-22s %-16s %s\n", bin2hex($in), $got, $got === $expected ? "ok" : "MISMATCH ($expected)");
}

// A NEL inside a word is a plain unencodable character, not a word break.
var_dump(dm_soundex("kat\u{0085}z"));
var_dump(dm_soundex("katz"));
?>
--EXPECT--
73096368               400000           ok
730a6368               400000           ok
731c6368               400000           ok
73206368               400000           ok
73e19a806368           400000           ok
73e280806368           400000           ok
73e280a86368           400000           ok
73e2819f6368           400000           ok
73e380806368           400000           ok
73c2856368             400000|450000    ok
73c2a06368             400000|450000    ok
73e280876368           400000|450000    ok
73e280af6368           400000|450000    ok
736368                 400000           ok
array(1) {
  [0]=>
  string(6) "534000"
}
array(1) {
  [0]=>
  string(6) "540000"
}
