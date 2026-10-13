--TEST--
BMPM output is bounded per input, including alternatives and nested prefix groups
--EXTENSIONS--
phonetic
--FILE--
<?php
$token = "rdzhaumssieoeeicczfeaumtschtei";
$input = $token . str_repeat("c", 1500) . $token;
foreach ([
    fn() => bmpm($input, BMPM_ASHKENAZI, BMPM_APPROX, "polish"),
    fn() => bmpm_match($input, "cohen", BMPM_ASHKENAZI, BMPM_APPROX, "polish"),
    fn() => bmpm_match("cohen", $input, BMPM_ASHKENAZI, BMPM_APPROX, "polish"),
    fn() => bmpm_match($input, $input, BMPM_ASHKENAZI, BMPM_APPROX, "polish"),
    fn() => bmpm("de  akeaxhaea"),
    fn() => bmpm("de de akeaxhaea"),
    fn() => bmpm(str_repeat(" ", 169) . $token . $token, BMPM_ASHKENAZI, BMPM_APPROX, "polish"),
] as $encode) {
    try {
        $encode();
        echo "unexpected success\n";
    } catch (ValueError $e) {
        echo $e->getMessage(), "\n";
    }
}

// Trimming preserves this 58,879-byte output; 230 input bytes allow 58,880.
$allowed = str_repeat(" ", 170) . $token . $token;
$out = bmpm($allowed, BMPM_ASHKENAZI, BMPM_APPROX, "polish");
var_dump(strlen($out));
echo hash("sha256", $out), "\n";
var_dump(bmpm_match($allowed, $allowed . " ", BMPM_ASHKENAZI, BMPM_APPROX, "polish"));

// A valid name with substantial expansion still produces every alternative.
$out = bmpm("de akeaxhaea");
var_dump(strlen($out));
echo hash("sha256", $out), "\n";
var_dump(bmpm("") === "");
echo "continued\n";
?>
--EXPECT--
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
phonetic: BMPM encode exceeds output size budget
int(58879)
26ee9ebc575c37e099948fd1543ee48069f40332e6ac3cff7ebe8188dc039669
bool(true)
int(2455)
fc3af763209fbb165c8f1bd7611b02de36775ff88701858ce06f188092d143c8
bool(true)
continued
