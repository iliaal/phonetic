--TEST--
double_metaphone(): empty, whitespace, single letters and leading vowels
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word, string $label): void {
    $code = double_metaphone($word);
    printf("%-8s [%s] | [%s]\n", $label, $code['primary'], $code['alternate']);
}

t("", "empty");
t(" ", "space");
t("\t\n\r ", "ws");
t("A", "A");
t("b", "b");
t("Z", "Z");
t("Q", "Q");
t("X", "X");
t("Otto", "Otto");
t(" Otto", "lead");
t("Otto ", "trail");
t(" KNIGHT", "leadKN");
t("Auto", "Auto");
t("Aaron", "Aaron");
t("over", "over");
?>
--EXPECT--
empty    [] | []
space    [] | []
ws       [] | []
A        [A] | [A]
b        [P] | [P]
Z        [S] | [S]
Q        [K] | [K]
X        [S] | [S]
Otto     [AT] | [AT]
lead     [AT] | [AT]
trail    [AT] | [AT]
leadKN   [NT] | [NT]
Auto     [AT] | [AT]
Aaron    [ARN] | [ARN]
over     [AFR] | [AFR]
