<?php
// Exercises the smart_str growth paths of a prebuilt module, so an import the
// running PHP does not export fails here: PHP loads extensions with RTLD_LAZY,
// and an unresolved function only aborts when first called.

function fail(string $msg): never
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

if (!extension_loaded('phonetic')) {
    fail('phonetic is not loaded');
}

// Double Metaphone folds the whole input into a smart_str before encoding, and
// an unlimited max_length grows both key buffers with the input.
$long = str_repeat('Schwarzenegger', 400);
$dm = double_metaphone($long, 0);
if (!is_array($dm) || !isset($dm['primary'], $dm['alternate'])) {
    fail('double_metaphone() did not return primary and alternate keys');
}
if (strlen($dm['primary']) < 2048 || !str_starts_with($dm['primary'], 'XRSN')) {
    fail('double_metaphone() primary key is not the expected long key');
}
if (double_metaphone('Schwarzenegger') !== ['primary' => 'XRSN', 'alternate' => 'XFRT']) {
    fail('double_metaphone() short key changed');
}

// dm_soundex() cleans its input into a smart_str; 4000 bytes stays under the
// 4096-byte input cap.
$dms = dm_soundex(str_repeat('Auerbach', 500));
if ($dms !== ['097479', '097579']) {
    fail('dm_soundex() on long input did not return the expected codes');
}
if (dm_soundex('Auerbach') !== ['097400', '097500']) {
    fail('dm_soundex() short codes changed');
}

// bmpm() joins its phoneme alternatives into a smart_str. The long input stays
// under the 4096-byte cap and the encode budgets.
if (bmpm('John Smith') !== 'ionzmit') {
    fail('bmpm() short encoding changed');
}
$bm = bmpm(str_repeat('a', 4000));
if (!is_string($bm) || $bm === '') {
    fail('bmpm() on long input did not return an encoding');
}

printf(
    "ok php=%s phonetic=%s dm_bytes=%d dms_codes=%d bmpm_bytes=%d\n",
    PHP_VERSION,
    phpversion('phonetic'),
    strlen($dm['primary']),
    count($dms),
    strlen($bm),
);
