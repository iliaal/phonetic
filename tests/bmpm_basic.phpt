--TEST--
bmpm(): default generic/approx auto-detected encoding
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach (["Jackson", "Schmidt", "Kowalski", "Mueller"] as $name) {
    printf("%-10s %s\n", $name, bmpm($name));
}
?>
--EXPECT--
Jackson    iakson|iaksun|iatskson|iatsksun|iokson|ioksun|iotskson|iotsksun|zakson|zokson
Schmidt    stzmit|zmit
Kowalski   kYvYlzki|kYvalzki|kYvolzki|kovYlzki|kovalzki|kovolzki|kowalzki|kowolzki|kuvalzki|kuvolzki
Mueller    mDlir|mlYr|mlir|mvilr|mvlYr|mvli|mvlir
