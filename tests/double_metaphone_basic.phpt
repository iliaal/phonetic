--TEST--
double_metaphone(): Philips canonical set, primary and alternate
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word): void {
    $code = double_metaphone($word);
    printf("%-14s %s | %s\n", $word, $code['primary'], $code['alternate']);
}

t("testing");
t("The");
t("quick");
t("brown");
t("fox");
t("jumped");
t("over");
t("the");
t("lazy");
t("dogs");
t("MacCafferey");
t("Stephan");
t("Kuczewski");
t("McClelland");
t("san jose");
t("xenophobia");
?>
--EXPECT--
testing        TSTN | TSTN
The            0 | T
quick          KK | KK
brown          PRN | PRN
fox            FKS | FKS
jumped         JMPT | AMPT
over           AFR | AFR
the            0 | T
lazy           LS | LS
dogs           TKS | TKS
MacCafferey    MKFR | MKFR
Stephan        STFN | STFN
Kuczewski      KSSK | KXFS
McClelland     MKLL | MKLL
san jose       SNHS | SNHS
xenophobia     SNFP | SNFP
