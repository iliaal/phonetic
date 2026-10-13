--TEST--
BMPM budget exceptions unwind, release local objects, and preserve later encodes
--EXTENSIONS--
phonetic
--FILE--
<?php
class BudgetGuard {
    public function __destruct() {
        echo "destroyed\n";
    }
}
function encodeWithGuard(callable $encode): void {
    $guard = new BudgetGuard;
    try {
        $encode();
        echo "unexpected success\n";
    } finally {
        echo "finally\n";
    }
}
// Padding permits the original phoneme-set limit to fire before the ratio cap.
foreach (["de akeaxhaeasz", str_repeat(" ", 20) . "de akeaxhaeasz"] as $input) {
    foreach ([
        fn() => bmpm($input),
        fn() => bmpm_match($input, "cohen"),
        fn() => bmpm_match("cohen", $input),
        fn() => bmpm_match($input, $input),
    ] as $encode) {
        try {
            encodeWithGuard($encode);
        } catch (ValueError $e) {
            echo $e->getMessage(), "\n";
        }
        var_dump(bmpm_match("Smith", "Smith"));
    }
}
echo "continued\n";
?>
--EXPECT--
finally
destroyed
phonetic: BMPM encode exceeds output size budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds output size budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds output size budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds output size budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds phoneme-set budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds phoneme-set budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds phoneme-set budget
bool(true)
finally
destroyed
phonetic: BMPM encode exceeds phoneme-set budget
bool(true)
continued
