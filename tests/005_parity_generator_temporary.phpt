--TEST--
Parity generator owns its temporary output and cleans it after failed replacement
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/phonetic-output-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/parity');
$jar = 'fixture jar';
file_put_contents($tmp . '/commons-codec-1.17.1.jar', $jar);
$source = file_get_contents($root . '/scripts/oracle/parity/generate.php');
// Derive the complete mode matrix from the production generator, not a
// separately maintained count. The mock retains real subprocess and I/O paths.
preg_match_all("/^    '([^']+)' => \\[\\[/m", $source, $matches);
if ($matches[1] === []) {
    throw new RuntimeException('could not extract generator modes');
}
$expected = implode('', array_map(static fn($mode) => "$mode\tSmith\tSMTH\n", $matches[1]));
$source = str_replace("['java', '-cp', \$jar, \$oracle]", '[PHP_BINARY, "-n", $oracle]', $source);
$source = preg_replace("/const CODEC_SHA256 = '[a-f0-9]+';/",
    "const CODEC_SHA256 = '" . hash('sha256', $jar) . "';", $source);
file_put_contents($tmp . '/parity/generate.php', $source);
file_put_contents($tmp . '/Oracle.java', '<?php while (($word = fgets(STDIN)) !== false) { echo rtrim($word, "\\r\\n"), "\\tSMTH\\n"; }');
file_put_contents($tmp . '/parity/words.txt', "Smith\n");

function run_generator(): array {
    global $tmp;
    $process = proc_open([PHP_BINARY, '-n', $tmp . '/parity/generate.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}
function check_files(string $label): void {
    global $tmp;
    $files = array_values(array_diff(scandir($tmp . '/parity'), ['.', '..']));
    if ($files !== ['generate.php', 'golden.tsv', 'golden.tsv.tmp', 'words.txt']) {
        echo "$label: missing sentinel or leftover temporary output\n";
    }
    if (!is_file($tmp . '/parity/golden.tsv.tmp')
        || file_get_contents($tmp . '/parity/golden.tsv.tmp') !== 'unrelated sentinel') {
        echo "$label: unrelated temporary file changed\n";
    }
}
try {
    file_put_contents($tmp . '/parity/golden.tsv', 'original golden');
    file_put_contents($tmp . '/parity/golden.tsv.tmp', 'unrelated sentinel');
    [$status, $stdout, $stderr] = run_generator();
    if ($status !== 0 || $stderr !== '' || file_get_contents($tmp . '/parity/golden.tsv') !== $expected) {
        echo "success: incomplete generation (status $status)\n$stdout$stderr";
    }
    check_files('success');

    unlink($tmp . '/parity/golden.tsv');
    mkdir($tmp . '/parity/golden.tsv');
    file_put_contents($tmp . '/parity/golden.tsv/marker', 'original directory');
    file_put_contents($tmp . '/parity/golden.tsv.tmp', 'unrelated sentinel');
    [$status, $stdout, $stderr] = run_generator();
    if ($status !== 2 || !str_contains($stderr, 'could not replace ')
        || !is_dir($tmp . '/parity/golden.tsv')
        || file_get_contents($tmp . '/parity/golden.tsv/marker') !== 'original directory') {
        echo "failure: expected rejection with target intact (status $status)\n$stdout$stderr";
    }
    check_files('failure');

    unlink($tmp . '/parity/golden.tsv/marker');
    rmdir($tmp . '/parity/golden.tsv');
    file_put_contents($tmp . '/parity/golden.tsv', 'original golden');
    // Force a collision with the unrelated sentinel. Exclusive creation must
    // reject it without truncating or deleting either existing file.
    $collisionSource = str_replace(
        "\$goldenFile . '.' . bin2hex(random_bytes(8)) . '.tmp'",
        "\$goldenFile . '.tmp'", $source, $replacements);
    if ($replacements !== 1) {
        echo "collision: temporary-path selector did not match exactly once\n";
    }
    file_put_contents($tmp . '/parity/generate.php', $collisionSource);
    [$status, $stdout, $stderr] = run_generator();
    if ($status !== 2 || !str_contains($stderr, 'could not create temporary output')
        || file_get_contents($tmp . '/parity/golden.tsv') !== 'original golden') {
        echo "collision: expected rejection with original golden intact (status $status)\n$stdout$stderr";
    }
    check_files('collision');
} finally {
    if (is_dir($tmp . '/parity/golden.tsv')) {
        unlink($tmp . '/parity/golden.tsv/marker');
        rmdir($tmp . '/parity/golden.tsv');
    }
    foreach (glob($tmp . '/parity/*') as $file) {
        unlink($file);
    }
    rmdir($tmp . '/parity');
    foreach (glob($tmp . '/*') as $file) {
        unlink($file);
    }
    rmdir($tmp);
}
echo "ok\n";
?>
--EXPECT--
ok
