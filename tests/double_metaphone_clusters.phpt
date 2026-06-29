--TEST--
double_metaphone(): CH / CC / SCH / TI / X consonant clusters
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word): void {
    $code = double_metaphone($word);
    printf("%-14s %s | %s\n", $word, $code['primary'], $code['alternate']);
}

t("character");
t("chianti");
t("Michael");
t("school");
t("Schmidt");
t("Schneider");
t("McClelland");
t("MacCafferey");
t("bacchus");
t("bellocchio");
t("focaccia");
t("accident");
t("Xavier");
t("box");
t("xenophobia");
t("nation");
t("portion");
t("Caesar");
?>
--EXPECT--
character      KRKT | KRKT
chianti        KNT | KNT
Michael        MKL | MXL
school         SKL | SKL
Schmidt        XMT | SMT
Schneider      XNTR | SNTR
McClelland     MKLL | MKLL
MacCafferey    MKFR | MKFR
bacchus        PKS | PKS
bellocchio     PLX | PLX
focaccia       FKX | FKX
accident       AKST | AKST
Xavier         SF | SFR
box            PKS | PKS
xenophobia     SNFP | SNFP
nation         NXN | NXN
portion        PRXN | PRXN
Caesar         SSR | SSR
