--TEST--
Parity generator preserves empty codes and whitespace while rejecting malformed oracle rows
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/phonetic-generate-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/parity');
// Use a PHP oracle fixture so this parser regression needs neither Java nor
// network access. Keep the production hash verification and all twelve modes.
$jar = 'fixture jar';
file_put_contents($tmp . '/commons-codec-1.17.1.jar', $jar);
$source = file_get_contents($root . '/scripts/oracle/parity/generate.php');
$source = str_replace(
    "['java', '-cp', \$jar, \$oracle]", '[PHP_BINARY, $oracle]', $source);
$source = preg_replace("/const CODEC_SHA256 = '[a-f0-9]+';/",
    "const CODEC_SHA256 = '" . hash('sha256', $jar) . "';", $source);
file_put_contents($tmp . '/parity/generate.php', $source);
file_put_contents($tmp . '/Oracle.java', '<?php echo file_get_contents(__DIR__ . "/output.txt");');

function generate_case(string $label, string $words, string $output, ?array $expected): void {
    global $tmp;
    file_put_contents($tmp . '/parity/words.txt', $words);
    file_put_contents($tmp . '/output.txt', $output);
    file_put_contents($tmp . '/parity/golden.tsv', 'unchanged');
    $process = proc_open([PHP_BINARY, '-n', $tmp . '/parity/generate.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $golden = file_get_contents($tmp . '/parity/golden.tsv');
    if ($expected === null) {
        if ($status !== 2 || $golden !== 'unchanged' || !str_contains($stderr, 'oracle ')) {
            echo "$label: expected rejection with original golden intact\n$stdout$stderr";
        }
    } else {
        $rows = explode("\n", rtrim($golden, "\n"));
        // Check each word/output pair in every mode, including set modes.
        $actual = array_map(static fn($row) => substr($row, strpos($row, "\t") + 1), $rows);
        if ($status !== 0 || $actual !== array_merge(...array_fill(0, 12, $expected))) {
            echo "$label: incorrect generated rows (status $status)\n$stdout$stderr";
        }
    }
}
try {
    foreach (["\n" => 'LF', "\r\n" => 'CRLF', "\r" => 'CR'] as $eol => $name) {
        generate_case("$name final empty", "Smith\nA\n", "Smith\tSMTH{$eol}A\t{$eol}", ["Smith\tSMTH", "A\t"]);
        generate_case("$name only empty", "A\n", "A\t{$eol}", ["A\t"]);
        generate_case("$name whitespace", " Smith\nJ\n", " Smith\tSMTH{$eol}J\tJ {$eol}", [" Smith\tSMTH", "J\tJ "]);
        generate_case("$name nonempty", "Smith\n", "Smith\tSMTH{$eol}", ["Smith\tSMTH"]);
        // Row-count and word/order guards must reject adjacent and blank-line
        // variants without replacing the existing golden file.
        generate_case("$name extra adjacent", "Smith\n", "Smith\tSMTH{$eol}A\t{$eol}", null);
        generate_case("$name extra blank", "Smith\n", "Smith\tSMTH{$eol}{$eol}", null);
        generate_case("$name missing tab adjacent", "Smith\nA\n", "Smith{$eol}A\t{$eol}", null);
        generate_case("$name missing tab blank", "Smith\nA\n", "Smith{$eol}{$eol}A\t{$eol}", null);
        generate_case("$name wrong word adjacent", "Smith\nA\n", "Jones\tJNS{$eol}A\t{$eol}", null);
        generate_case("$name wrong word blank", "Smith\nA\n", "Jones\tJNS{$eol}{$eol}A\t{$eol}", null);
    }
    generate_case('unterminated empty', "A\n", "A\t", ["A\t"]);
    generate_case('unterminated nonempty', "Smith\n", "Smith\tSMTH", ["Smith\tSMTH"]);
    generate_case('no output', "A\n", '', null);
} finally {
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
