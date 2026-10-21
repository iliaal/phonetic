--TEST--
bmpm_match(): identical prefix groups without phonetic tokens do not match
--EXTENSIONS--
phonetic
--FILE--
<?php
foreach ([BMPM_APPROX, BMPM_EXACT] as $accuracy) {
    foreach (['arabic', 'hebrew', 'greek'] as $language) {
        foreach (['van Smith', 'van van Smith', "d'Smith"] as $name) {
            $code = bmpm($name, BMPM_GENERIC, $accuracy, $language);
            if ($code === '' || preg_match('/^[()|\-]+$/D', $code) !== 1) {
                throw new Exception('Expected nonempty grouping with no tokens');
            }
            if (bmpm_match($name, $name, BMPM_GENERIC, $accuracy, $language)) {
                throw new Exception('Empty token sets must not match');
            }
        }
    }
}
echo "empty prefix groups: ok\n";
// Preserve ordinary self-matches and the nonidentical intersection path.
var_dump(bmpm_match('van Smith', 'van Smith'));
var_dump(bmpm_match("d'Angelo", "d'Angelo"));
var_dump(bmpm_match('van Smith', 'Smith'));
var_dump(bmpm_match('van Smith', 'van Cohen', BMPM_GENERIC, BMPM_APPROX, 'arabic'));
var_dump(bmpm_match('Smith', 'Smith', BMPM_GENERIC, BMPM_APPROX, 'arabic'));
var_dump(bmpm_match('', ''));
?>
--EXPECT--
empty prefix groups: ok
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
