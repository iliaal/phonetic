--TEST--
bmpm(): encodings of a generated corpus match the digest recorded before rule-context compilation
--EXTENSIONS--
phonetic
--FILE--
<?php
// The digest covers every name type and accuracy, plus each named forced
// language at approximate accuracy. It was recorded from the build before
// rule contexts were compiled at MINIT.
require __DIR__ . '/bmpm_corpus.inc';
echo bmpm_corpus_digest(bmpm_corpus(4000, 20261114)), "\n";
?>
--EXPECT--
37654ca0ec1885ed8a09b35359cc2088931b0f11c6145a598c2423d2fdbcff5a
