--TEST--
bmpm(): language guessing still applies rules keyed on non-ASCII letters
--EXTENSIONS--
phonetic
--FILE--
<?php
// Each name contains a non-ASCII letter that a language-guessing rule keys on.
// If guessing skipped those rules, the guessed language and the encoding change.
foreach (['Müller', 'Gonçalves', 'Łukasiewicz', 'Dvořák', 'Nuñez', 'Großmann', 'Иванов', 'Kovács'] as $n) {
    echo $n, ': ', bmpm($n), "\n";
}
echo 'Ashkenazi Müller: ', bmpm('Müller', BMPM_ASHKENAZI), "\n";
echo 'Sephardic Nuñez: ', bmpm('Nuñez', BMPM_SEPHARDIC), "\n";
?>
--EXPECT--
Müller: mlYr|mlir|mulir
Gonçalves: gontsalvis|gontsolvis|gonzalbis|gonzalvi|gonzalviS|gonzalvis|gonzolbis|gonzolvi|gonzolviS|gonzolvis
Łukasiewicz: lukinsivitS|lukinsivits|lukonsivitS|lukonsivits|lukosivitS|lukosivits
Dvořák: dvorak|dvorok|dvorzak|dvorzok
Nuñez: nuniS|nuniiS|nuniis|nunis
Großmann: grYzman|grYzmon|grozman|grozmon
Иванов: Qvanof|Qvonof|ivanof|ivonof
Kovács: kovatS|kovats|kovotS|kovots
Ashkenazi Müller: mQlir
Sephardic Nuñez: nuniS|nunis
