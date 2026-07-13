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

echo "arginfo hash matches phonetic.stub.php\n";
