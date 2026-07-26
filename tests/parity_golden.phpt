--TEST--
Oracle golden.tsv parity matrix (Commons Codec 1.17.1, 550 rows)
--EXTENSIONS--
phonetic
--SKIPIF--
<?php
$root = dirname(__DIR__);
if (!is_file($root . '/scripts/oracle/parity/golden.tsv')
		|| !is_file($root . '/scripts/oracle/parity/words.txt')
		|| !is_file($root . '/scripts/oracle/parity/check.php')) {
	die('skip oracle parity fixtures not present');
}
?>
--FILE--
<?php
/* Re-invoke the same CLI checker CI runs, with the PHPT harness's PHP and
 * the already-configured extension load path from TEST_PHP_ARGS / modules/. */
$root = dirname(__DIR__);
$bin = getenv('TEST_PHP_EXECUTABLE') ?: PHP_BINARY;
$cmd = escapeshellarg($bin);
$args = getenv('TEST_PHP_ARGS');
if (is_string($args) && $args !== '') {
	$cmd .= ' ' . $args;
} else {
	$so = $root . '/modules/phonetic.so';
	if (is_file($so)) {
		$cmd .= ' -d extension=' . escapeshellarg($so);
	}
}
$cmd .= ' ' . escapeshellarg($root . '/scripts/oracle/parity/check.php') . ' 2>&1';
passthru($cmd, $exit);
if ($exit !== 0) {
	fwrite(STDERR, "parity check failed with exit $exit\n");
	exit($exit);
}
?>
--EXPECT--
parity: 550 checked, 0 mismatch(es)
