--TEST--
bmpm(): repeated encodes free final-pass phoneme texts that merge into an existing phoneme
--EXTENSIONS--
phonetic
--SKIPIF--
<?php
if (getenv('USE_ZEND_ALLOC') === '0') die('skip memory_get_usage() does not track the system allocator');
?>
--FILE--
<?php
// This multi-word input sends heap-allocated (over 23-byte) phoneme texts
// through the final pass, where some merge into a phoneme already in the
// result set. Each merged text must be freed, so after warm-up repeated
// encodes leave Zend memory usage unchanged (a leak grows it by over 2 KiB
// per call).
$s = 'Dzieduszycki Szczepanski Przybylski';
$first = bmpm($s);
for ($i = 0; $i < 5; $i++) {
    $r = bmpm($s);
}
$before = memory_get_usage();
for ($i = 0; $i < 20; $i++) {
    $r = bmpm($s);
}
var_dump(memory_get_usage() - $before < 4096);
var_dump($r === $first);
?>
--EXPECT--
bool(true)
bool(true)
