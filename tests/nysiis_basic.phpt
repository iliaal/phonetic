--TEST--
nysiis(): strict mode (default, max 6) against Commons Codec golden vectors
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    [["Brian", "Brown", "Brun"], "BRAN"],
    [["Capp", "Cope", "Copp", "Kipp"], "CAP"],
    [["Dane", "Dean", "Dionne"], "DAN"],
    [["Dent"], "DAD"],
    [["Trueman", "Truman"], "TRANAN"],
    [["Schmidt"], "SNAD"],
    [["Smith", "Schmit"], "SNAT"],
    [["Phil"], "FAL"],
    [["Cory", "Corey", "Kory"], "CARY"],
    [["Kobwick"], "CABWAC"],
    [["Kocher"], "CACAR"],
];
foreach ($cases as [$names, $expected]) {
    foreach ($names as $name) {
        $got = nysiis($name);
        printf("%-10s %-8s %s\n", $name, $got, $got === $expected ? "ok" : "FAIL($expected)");
    }
}
echo nysiis("O'Daniel"), "\n";
echo nysiis("O'Donnel"), "\n";
echo nysiis("FUZZY"), "\n";
?>
--EXPECT--
Brian      BRAN     ok
Brown      BRAN     ok
Brun       BRAN     ok
Capp       CAP      ok
Cope       CAP      ok
Copp       CAP      ok
Kipp       CAP      ok
Dane       DAN      ok
Dean       DAN      ok
Dionne     DAN      ok
Dent       DAD      ok
Trueman    TRANAN   ok
Truman     TRANAN   ok
Schmidt    SNAD     ok
Smith      SNAT     ok
Schmit     SNAT     ok
Phil       FAL      ok
Cory       CARY     ok
Corey      CARY     ok
Kory       CARY     ok
Kobwick    CABWAC   ok
Kocher     CACAR    ok
ODANAL
ODANAL
FASY
