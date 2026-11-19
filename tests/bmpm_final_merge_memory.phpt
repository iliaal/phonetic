--TEST--
bmpm(): repeated encodes free final-pass phoneme texts that merge into an existing phoneme
--EXTENSIONS--
phonetic
--FILE--
<?php
// This multi-word input sends heap-allocated (over 23-byte) phoneme texts
// through the final pass, where some merge into a phoneme already in the
// result set. Each merged text must be freed, so after warm-up repeated
// encodes leave Zend memory usage unchanged (a leak grows it by over 2 KiB
// per call). With USE_ZEND_ALLOC=0 (the ASan lanes) memory_get_usage() does
// not see these allocations; LeakSanitizer reports such a leak there instead.
$s = 'Dzieduszycki Szczepanski Przybylski';
$first = bmpm($s);
for ($i = 0; $i < 5; $i++) {
    $r = bmpm($s);
}
$before = memory_get_usage();
for ($i = 0; $i < 20; $i++) {
    $r = bmpm($s);
}
$tracked = getenv('USE_ZEND_ALLOC') !== '0';
var_dump(!$tracked || memory_get_usage() - $before < 4096);
var_dump($r === $first);
?>
--EXPECT--
bool(true)
bool(true)
