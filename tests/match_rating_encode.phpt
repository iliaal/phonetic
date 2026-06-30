--TEST--
match_rating(): MRA codex against Commons Codec golden vectors
--EXTENSIONS--
phonetic
--FILE--
<?php
$cases = [
    "HARPER" => "HRPR",
    ""       => "",
    "E"      => "",
    "Smith"  => "SMTH",
    "Smyth"  => "SMYTH",
    " "      => "",
    ".,-"    => "",
    "uoiea.,-AEIOU" => "U",
    "aeiouAEIOU"    => "A",
];
foreach ($cases as $in => $exp) {
    $got = match_rating($in);
    printf("[%-13s] %-6s %s\n", $in, $got, $got === $exp ? "ok" : "FAIL($exp)");
}
?>
--EXPECT--
[HARPER       ] HRPR   ok
[             ]        ok
[E            ]        ok
[Smith        ] SMTH   ok
[Smyth        ] SMYTH  ok
[             ]        ok
[.,-          ]        ok
[uoiea.,-AEIOU] U      ok
[aeiouAEIOU   ] A      ok
