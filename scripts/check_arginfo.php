<?php

$root = dirname(__DIR__);
$stubFile = $root . '/phonetic.stub.php';
$arginfoFile = $root . '/phonetic_arginfo.h';
$stub = file_get_contents($stubFile);
$arginfo = file_get_contents($arginfoFile);

if ($stub === false || $arginfo === false) {
    fwrite(STDERR, "could not read stub or generated arginfo\n");
    exit(2);
}
if (!preg_match('/\* Stub hash: ([0-9a-f]{40}) \*/', $arginfo, $matches)) {
    fwrite(STDERR, "phonetic_arginfo.h has no generated stub hash\n");
    exit(2);
}

$expected = sha1(str_replace("\r\n", "\n", $stub));
if (!hash_equals($expected, $matches[1])) {
    fwrite(STDERR, "phonetic_arginfo.h is stale; regenerate it from phonetic.stub.php\n");
    exit(1);
}

// Accuracy ints are dual-owned: stub registers public BMPM_* and C validates
// with PH_BMPM_*. Keep them locked together (stub is the public source).
$cFile = $root . '/src/bmpm.c';
$c = file_get_contents($cFile);
if ($c === false) {
    fwrite(STDERR, "could not read src/bmpm.c\n");
    exit(2);
}
foreach (['APPROX' => 10, 'EXACT' => 20] as $name => $want) {
    if (!preg_match('/const\s+BMPM_' . $name . '\s*=\s*(\d+)\s*;/', $stub, $sm)) {
        fwrite(STDERR, "stub missing BMPM_{$name}\n");
        exit(2);
    }
    if (!preg_match('/#define\s+PH_BMPM_' . $name . '\s+(\d+)/', $c, $cm)) {
        fwrite(STDERR, "src/bmpm.c missing PH_BMPM_{$name}\n");
        exit(2);
    }
    if ((int) $sm[1] !== $want || (int) $cm[1] !== $want || (int) $sm[1] !== (int) $cm[1]) {
        fwrite(STDERR, "BMPM_{$name} mismatch: stub={$sm[1]} C={$cm[1]} expected={$want}\n");
        exit(1);
    }
}

echo "arginfo hash matches phonetic.stub.php\n";
echo "PH_BMPM_* accuracy values match stub\n";
