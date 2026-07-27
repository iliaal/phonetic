--TEST--
dm_soundex(): whitespace stripping follows Java Character.isWhitespace exactly
--EXTENSIONS--
phonetic
--FILE--
<?php
/* Commons Codec's DaitchMokotoffSoundex.cleanup() drops Character.isWhitespace
 * code points and keeps everything else. That set is exactly 25 code points; it
 * excludes the no-break spaces (U+00A0, U+2007, U+202F) *and* U+0085 NEXT LINE,
 * which Python's str.isspace() and .NET's char.IsWhiteSpace do treat as space.
 * Expected values come from the pinned oracle (commons-codec 1.17.1).
 *
 * A stripped separator lets "s" and "ch" fuse into the sch digraph ("400000");
 * a kept one breaks it, so the encoder branches ("400000|450000"). */
$stripped = ["\t", "\n", "\x1c", " ", "\u{1680}", "\u{2000}", "\u{2028}", "\u{205f}", "\u{3000}"];
$kept     = ["\u{0085}", "\u{00a0}", "\u{2007}", "\u{202f}"];

foreach ($stripped as $sep) {
    echo bin2hex($sep), " => ", implode("|", dm_soundex("s{$sep}ch")), "\n";
}
foreach ($kept as $sep) {
    echo bin2hex($sep), " => ", implode("|", dm_soundex("s{$sep}ch")), "\n";
}
echo "none => ", implode("|", dm_soundex("sch")), "\n";

// A NEL inside a word is a plain unencodable character, not a word break.
var_dump(dm_soundex("kat\u{0085}z"));
var_dump(dm_soundex("katz"));
?>
--EXPECT--
09 => 400000
0a => 400000
1c => 400000
20 => 400000
e19a80 => 400000
e28080 => 400000
e280a8 => 400000
e2819f => 400000
e38080 => 400000
c285 => 400000|450000
c2a0 => 400000|450000
e28087 => 400000|450000
e280af => 400000|450000
none => 400000
array(1) {
  [0]=>
  string(6) "534000"
}
array(1) {
  [0]=>
  string(6) "540000"
}
