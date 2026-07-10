--TEST--
double_metaphone(): published Philips algorithm parity (Commons Codec oracle)
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word): void {
    $code = double_metaphone($word);
    printf("%-10s %s|%s\n", $word, $code['primary'], $code['alternate']);
}

t("thumb");
t("dumb");
t("high");
t("hugh");
t("agha");
t("jose");
t("san jose");
t("brigier");
t("angiera");
t("magiera");
t("brigiere");
t("mooch");
t("pooch");
t("thumbs");
t("thumber");
t("plumber");
t("ghost");
t("aghast");
?>
--EXPECT--
thumb      0M|TM
dumb       TM|TM
high       H|H
hugh       H|H
agha       AK|AK
jose       HS|HS
san jose   SNHS|SNHS
brigier    PRJ|PRJR
angiera    ANJR|ANJR
magiera    MJR|MJR
brigiere   PRJR|PRJR
mooch      MK|MK
pooch      PK|PK
thumbs     0MPS|TMPS
thumber    0MR|TMR
plumber    PLMR|PLMR
ghost      KST|KST
aghast     AKST|AKST
