--TEST--
dm_soundex(): codes and matches for a generated corpus match the digest recorded before word-sized code comparison
--EXTENSIONS--
phonetic
--FILE--
<?php
require __DIR__ . '/dm_soundex_corpus.inc';
echo dm_corpus_digest(dm_corpus(6000, 20261114)), "\n";
?>
--EXPECT--
a9d4ee9e76e08626472e9473d3acd46552b5592592a6b14074debc57d982aecc
