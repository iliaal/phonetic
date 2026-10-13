--TEST--
dm_soundex(): repeated transitions preserve every retained code and invalidate on state changes
--EXTENSIONS--
phonetic
--FILE--
<?php
$input = 'cc' . str_repeat('ję', 1364) . 'ja';
echo strlen($input), "\n";
echo implode('|', dm_soundex($input)), "\n";
var_dump(dm_soundex_match($input, str_replace('ę', 'ą', $input)));
var_dump(dm_soundex_match($input, 'A'));

// A later rule still updates partial branches after a repeated transition.
$prefix = 'cc' . str_repeat('ję', 20);
foreach (['', 'r', 'mn', 'a', 'ch', 'mn' . str_repeat('ję', 20) . 'r'] as $suffix) {
    $codes = dm_soundex($prefix . $suffix);
    echo count($codes), ' ', hash('sha256', implode('|', $codes)), "\n";
}

// The same replacement must append when an mn/nm pair forces it.
echo implode('|', dm_soundex(str_repeat('m', 100) . 'n')), "\n";
echo implode('|', dm_soundex(str_repeat('n', 100) . 'm')), "\n";
echo implode('|', dm_soundex(str_repeat('m', 100) . '1n')), "\n";
echo implode('|', dm_soundex(str_repeat('n', 100) . '1m')), "\n";
?>
--EXPECT--
4096
400000|440000|460000|464000|444000|446000|446400|466000|466400|464400|464600|464640|444400|444600|444640|446600|446640|446440|446460|446464|466600|466640|466440|466460|466464|464440|464460|464464|464660|464664|464644|464646|444440|444460|444464|444660|444664|444644|444646|446660|446664|446644|446646|446444|446446|446466|466660|466664|466644|466646|466444|466446|466466|464444|464446|464466|464666|444444|444446|444466|444666|446666|466666|450000|454000|456000|456400|454400|454600|454640|456600|456640|456440|456460|456464|454440|454460|454464|454660|454664|454644|454646|456660|456664|456644|456646|456444|456446|456466|454444|454446|454466|454666|456666|540000|544000|546000|546400|544400|544600|544640|546600|546640|546440|546460|546464|544440|544460|544464|544660|544664|544644|544646|546660|546664|546644|546646|546444|546446|546466|544444|544446|544466|544666|546666|500000|560000|564000
bool(true)
bool(false)
128 413789a8e50d42fe9ded38cbab2231ff3fb5fab6d7958ef1ea8b2596acdd68aa
128 beea76d3775bc62f93d1f7e461912c6c3458afbd5ad26aaf7c475ebf13572972
80 4f7c44b03222bb1db0a17a7c09d76bdd8e01e127e6fc5701218088fb0de1f510
128 5d8c7b9d7a3edcbf7c8cf3b9be16079cae606efbf99d97ee3e30da5bbf406d58
128 90f15444608604593579cd741c74a3b871ef05cc204153fd4a5fe6bf8683e414
99 5e566f0a9f46296e1f9079dd735c666ea7ebbef2e5e414a8732683523d369a1e
666000
666000
660000
660000
