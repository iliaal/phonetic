--TEST--
dm_soundex(): fork-alternation input saturates the branch set and truncates to the first 128 codes
--EXTENSIONS--
phonetic
--FILE--
<?php
// Same fork-alternation shape as dm_soundex_branch_cap.phpt: the live branch
// set saturates within ~100 bytes. Past 128 distinct codes the set keeps the
// first 128 in insertion order, drops later ones, and keeps encoding instead
// of failing, so the call returns a deterministic truncated set.
$e = "\xC4\x99"; // U+0119 e-ogonek
$t = "\xC5\xA3"; // U+0163 t-cedilla
$cyc = str_replace(" ", "", "{$e}j{$e}j{$e}j{$e}{$t}c{$t}c{$t}rsc{$t}crsc{$t}cccc c{$e}j{$e}j{$e}l{$e}j{$e}cc");
var_dump(dm_soundex(substr(str_repeat($cyc, 200), 0, 4090)));
?>
--EXPECT--
array(128) {
  [0]=>
  string(6) "343434"
  [1]=>
  string(6) "343439"
  [2]=>
  string(6) "343435"
  [3]=>
  string(6) "343484"
  [4]=>
  string(6) "343485"
  [5]=>
  string(6) "343486"
  [6]=>
  string(6) "343468"
  [7]=>
  string(6) "343448"
  [8]=>
  string(6) "343446"
  [9]=>
  string(6) "343466"
  [10]=>
  string(6) "343464"
  [11]=>
  string(6) "343444"
  [12]=>
  string(6) "343458"
  [13]=>
  string(6) "343456"
  [14]=>
  string(6) "343454"
  [15]=>
  string(6) "343453"
  [16]=>
  string(6) "343494"
  [17]=>
  string(6) "343459"
  [18]=>
  string(6) "343534"
  [19]=>
  string(6) "343539"
  [20]=>
  string(6) "343543"
  [21]=>
  string(6) "343548"
  [22]=>
  string(6) "343546"
  [23]=>
  string(6) "343544"
  [24]=>
  string(6) "343545"
  [25]=>
  string(6) "343549"
  [26]=>
  string(6) "343943"
  [27]=>
  string(6) "343948"
  [28]=>
  string(6) "343946"
  [29]=>
  string(6) "343944"
  [30]=>
  string(6) "343945"
  [31]=>
  string(6) "343949"
  [32]=>
  string(6) "343594"
  [33]=>
  string(6) "343584"
  [34]=>
  string(6) "343585"
  [35]=>
  string(6) "343586"
  [36]=>
  string(6) "343568"
  [37]=>
  string(6) "343566"
  [38]=>
  string(6) "343564"
  [39]=>
  string(6) "348434"
  [40]=>
  string(6) "348435"
  [41]=>
  string(6) "348443"
  [42]=>
  string(6) "348448"
  [43]=>
  string(6) "348446"
  [44]=>
  string(6) "348444"
  [45]=>
  string(6) "348445"
  [46]=>
  string(6) "348449"
  [47]=>
  string(6) "348463"
  [48]=>
  string(6) "348464"
  [49]=>
  string(6) "348466"
  [50]=>
  string(6) "348453"
  [51]=>
  string(6) "348454"
  [52]=>
  string(6) "348456"
  [53]=>
  string(6) "348543"
  [54]=>
  string(6) "348544"
  [55]=>
  string(6) "348546"
  [56]=>
  string(6) "348534"
  [57]=>
  string(6) "348535"
  [58]=>
  string(6) "348548"
  [59]=>
  string(6) "348545"
  [60]=>
  string(6) "348549"
  [61]=>
  string(6) "348563"
  [62]=>
  string(6) "348564"
  [63]=>
  string(6) "348566"
  [64]=>
  string(6) "348643"
  [65]=>
  string(6) "348644"
  [66]=>
  string(6) "348646"
  [67]=>
  string(6) "348645"
  [68]=>
  string(6) "348654"
  [69]=>
  string(6) "348653"
  [70]=>
  string(6) "348656"
  [71]=>
  string(6) "348465"
  [72]=>
  string(6) "348664"
  [73]=>
  string(6) "348665"
  [74]=>
  string(6) "346843"
  [75]=>
  string(6) "346844"
  [76]=>
  string(6) "346846"
  [77]=>
  string(6) "346845"
  [78]=>
  string(6) "346854"
  [79]=>
  string(6) "346853"
  [80]=>
  string(6) "346856"
  [81]=>
  string(6) "346864"
  [82]=>
  string(6) "346865"
  [83]=>
  string(6) "346866"
  [84]=>
  string(6) "344843"
  [85]=>
  string(6) "344844"
  [86]=>
  string(6) "344846"
  [87]=>
  string(6) "344845"
  [88]=>
  string(6) "344854"
  [89]=>
  string(6) "344853"
  [90]=>
  string(6) "344856"
  [91]=>
  string(6) "344864"
  [92]=>
  string(6) "344865"
  [93]=>
  string(6) "344866"
  [94]=>
  string(6) "344684"
  [95]=>
  string(6) "344685"
  [96]=>
  string(6) "344686"
  [97]=>
  string(6) "346684"
  [98]=>
  string(6) "346685"
  [99]=>
  string(6) "346686"
  [100]=>
  string(6) "346484"
  [101]=>
  string(6) "346485"
  [102]=>
  string(6) "346486"
  [103]=>
  string(6) "346468"
  [104]=>
  string(6) "344484"
  [105]=>
  string(6) "344485"
  [106]=>
  string(6) "344486"
  [107]=>
  string(6) "344468"
  [108]=>
  string(6) "344668"
  [109]=>
  string(6) "344648"
  [110]=>
  string(6) "344646"
  [111]=>
  string(6) "346668"
  [112]=>
  string(6) "346648"
  [113]=>
  string(6) "346646"
  [114]=>
  string(6) "346448"
  [115]=>
  string(6) "346446"
  [116]=>
  string(6) "346466"
  [117]=>
  string(6) "346464"
  [118]=>
  string(6) "345843"
  [119]=>
  string(6) "345844"
  [120]=>
  string(6) "345846"
  [121]=>
  string(6) "345845"
  [122]=>
  string(6) "345854"
  [123]=>
  string(6) "345853"
  [124]=>
  string(6) "345856"
  [125]=>
  string(6) "345864"
  [126]=>
  string(6) "345865"
  [127]=>
  string(6) "345866"
}
