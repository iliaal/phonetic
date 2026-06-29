--TEST--
bmpm(): silent alternative and >20 final phonemes after dedup
--EXTENSIONS--
phonetic
--FILE--
<?php
// The "-owa" suffix injects a silent alternative (yielding short forms such as
// "koval"); the final transcription passes are not capped at 20, so the
// deduplicated result legitimately exceeds the maxPhonemes ceiling.
$out = bmpm("Kowalowa");
$parts = explode("|", $out);
var_dump(count($parts));
var_dump(in_array("koval", $parts, true));
echo $out, "\n";
?>
--EXPECT--
int(32)
bool(true)
kYvYlova|kYvYlovo|kYvalova|kYvalovo|kYvolova|kYvolovo|kovYlova|kovYlovo|kovail|koval|kovalof|kovalova|kovalovo|kovoil|kovol|kovolof|kovolova|kovolovo|kowalova|kowalovo|kowolova|kowolovo|kuvail|kuval|kuvalof|kuvalova|kuvalovo|kuvoil|kuvol|kuvolof|kuvolova|kuvolovo
