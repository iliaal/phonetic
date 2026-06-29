--TEST--
double_metaphone(): UTF-8 accented Latin folded to base ASCII
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word, string $label): void {
    $code = double_metaphone($word);
    printf("%-8s %s | %s\n", $label, $code['primary'], $code['alternate']);
}

// n-tilde folds to N (canonical Double Metaphone behaviour).
t("\u{00F1}", "ntilde");
// Combining marks (NFD form: n + U+0303) fold the same way.
t("n\u{0303}", "nfd");
t("r\u{00E9}sum\u{00E9}", "resume");
// C-cedilla carries the /s/ sound, not /k/.
t("fa\u{00E7}ade", "facade");
t("na\u{00EF}ve", "naive");
t("M\u{00FC}ller", "muller");
t("G\u{00F6}del", "godel");
?>
--EXPECT--
ntilde   N | N
nfd      N | N
resume   RSM | RSM
facade   FST | FST
naive    NF | NF
muller   MLR | MLR
godel    KTL | KTL
