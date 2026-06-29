--TEST--
double_metaphone(): silent leading GN / KN / PN / WR / PS
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word): void {
    $code = double_metaphone($word);
    printf("%-14s %s | %s\n", $word, $code['primary'], $code['alternate']);
}

t("Gnarly");
t("Knight");
t("Pneumonia");
t("Wright");
t("Psalm");
t("Wrack");
t("Knack");
t("Gnaw");
t("Psycho");
?>
--EXPECT--
Gnarly         NRL | NRL
Knight         NT | NT
Pneumonia      NMN | NMN
Wright         RT | RT
Psalm          SLM | SLM
Wrack          RK | RK
Knack          NK | NK
Gnaw           N | NF
Psycho         SX | SK
