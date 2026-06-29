--TEST--
double_metaphone(): max_length truncation and unlimited
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word, int $max): void {
    $code = double_metaphone($word, $max);
    printf("%-14s %3d  %s | %s\n", $word, $max, $code['primary'], $code['alternate']);
}

t("jumped", 4);
t("jumped", 3);
t("jumped", 2);
t("jumped", 1);
t("jumped", 0);
t("jumped", -1);
t("encyclopedia", 4);
t("encyclopedia", 0);
?>
--EXPECT--
jumped           4  JMPT | AMPT
jumped           3  JMP | AMP
jumped           2  JM | AM
jumped           1  J | A
jumped           0  JMPT | AMPT
jumped          -1  JMPT | AMPT
encyclopedia     4  ANSK | ANSK
encyclopedia     0  ANSKLPT | ANSKLPT
