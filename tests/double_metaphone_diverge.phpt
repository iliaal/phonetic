--TEST--
double_metaphone(): primary and alternate codes diverge
--EXTENSIONS--
phonetic
--FILE--
<?php
function t(string $word): void {
    $code = double_metaphone($word);
    printf("%-14s %s | %s\n", $word, $code['primary'], $code['alternate']);
}

t("Smith");
t("Schmidt");
t("Jablonski");
t("Yablonsky");
t("Wasserman");
t("Vasserman");
t("Michael");
t("Thumb");
t("Xavier");
t("Joqqi");
t("Czerny");
t("Kutchefski");
?>
--EXPECT--
Smith          SM0 | XMT
Schmidt        XMT | SMT
Jablonski      JPLN | APLN
Yablonsky      APLN | APLN
Wasserman      ASRM | FSRM
Vasserman      FSRM | FSRM
Michael        MKL | MXL
Thumb          0MP | TMP
Xavier         SF | SFR
Joqqi          JK | AK
Czerny         SRN | XRN
Kutchefski     KXFS | KXFS
