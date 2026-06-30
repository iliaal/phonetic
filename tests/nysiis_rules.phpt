--TEST--
nysiis(): per-rule prefix/suffix/transcode cases and non-strict full codes
--EXTENSIONS--
phonetic
--FILE--
<?php
// Strict, exercising one rule at a time (Commons Codec rule tests).
$rules = [
    "MACX" => "MCX", "KNX" => "NX", "KX" => "CX", "PHX" => "FX",
    "PFX" => "FX", "SCHX" => "SX",
    "XEE" => "XY", "XIE" => "XY", "XDT" => "XD", "XRT" => "XD",
    "XRD" => "XD", "XNT" => "XD", "XND" => "XD",
    "XEV" => "XAF", "XAX" => "XAX", "XEX" => "XAX", "XIX" => "XAX",
    "XOX" => "XAX", "XUX" => "XAX",
    "XQ" => "XG", "XZ" => "X", "XM" => "XN",
    "XS" => "X", "XSS" => "X",
    "XAY" => "XY", "XAYS" => "XY", "XA" => "X", "XAS" => "X",
];
foreach ($rules as $in => $exp) {
    $got = nysiis($in);
    echo "$in => $got", $got === $exp ? "" : " FAIL($exp)", "\n";
}

echo "--- non-strict (max_length = 0, full code) ---\n";
$full = [
    "MACINTOSH" => "MCANT", "KNUTH" => "NAT", "PHILLIPSON" => "FALAPSAN",
    "PFEISTER" => "FASTAR", "SCHOENHOEFT" => "SANAFT", "WESTERLUND" => "WASTARLAD",
    "CASSTEVENS" => "CASTAFAN", "MCKNIGHT" => "MCNAGT", "DEUTSCH" => "DAT",
];
foreach ($full as $in => $exp) {
    $got = nysiis($in, 0);
    echo "$in => $got", $got === $exp ? "" : " FAIL($exp)", "\n";
}
?>
--EXPECT--
MACX => MCX
KNX => NX
KX => CX
PHX => FX
PFX => FX
SCHX => SX
XEE => XY
XIE => XY
XDT => XD
XRT => XD
XRD => XD
XNT => XD
XND => XD
XEV => XAF
XAX => XAX
XEX => XAX
XIX => XAX
XOX => XAX
XUX => XAX
XQ => XG
XZ => X
XM => XN
XS => X
XSS => X
XAY => XY
XAYS => XY
XA => X
XAS => X
--- non-strict (max_length = 0, full code) ---
MACINTOSH => MCANT
KNUTH => NAT
PHILLIPSON => FALAPSAN
PFEISTER => FASTAR
SCHOENHOEFT => SANAFT
WESTERLUND => WASTARLAD
CASSTEVENS => CASTAFAN
MCKNIGHT => MCNAGT
DEUTSCH => DAT
