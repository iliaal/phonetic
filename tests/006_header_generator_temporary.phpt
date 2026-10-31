--TEST--
Header generator owns temporary outputs and preserves unrelated files
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/phonetic-headers-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/scripts');
mkdir($tmp . '/src');
$source = file_get_contents($root . '/scripts/gen_bmpm_data.php');
// Keep the real vendored inputs while placing both output headers in isolation.
$source = str_replace("\$vendor  = \$root . '/vendor/commons-codec-bm';",
    '$vendor = ' . var_export($root . '/vendor/commons-codec-bm', true) . ';', $source);
$headers = ['bmpm_data.h', 'dm_data.h'];
function run_generator(string $source): array {
    global $tmp;
    file_put_contents($tmp . '/scripts/gen_bmpm_data.php', $source);
    $process = proc_open([PHP_BINARY, '-n', $tmp . '/scripts/gen_bmpm_data.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}
function check_sentinels(string $label): void {
    global $tmp, $headers;
    foreach ($headers as $header) {
        if (!is_file("$tmp/src/$header.tmp")
            || file_get_contents("$tmp/src/$header.tmp") !== 'unrelated sentinel') {
            echo "$label: $header sentinel changed\n";
        }
    }
    if (count(scandir($tmp . '/src')) !== 6) {
        echo "$label: missing file or leaked temporary\n";
    }
}
try {
    foreach ($headers as $header) {
        file_put_contents("$tmp/src/$header", 'original header');
        file_put_contents("$tmp/src/$header.tmp", 'unrelated sentinel');
    }
    [$status, $stdout, $stderr] = run_generator($source);
    if ($status !== 0 || $stderr !== '') {
        echo "success: unexpected status $status\n$stdout$stderr";
    }
    foreach ($headers as $header) {
        if (file_get_contents("$tmp/src/$header") !== file_get_contents("$root/src/$header")) {
            echo "success: $header differs from checked-in output\n";
        }
    }
    check_sentinels('success');

    foreach ($headers as $header) {
        // Exercise failed replacement independently for each shared writer call.
        unlink("$tmp/src/$header");
        mkdir("$tmp/src/$header");
        file_put_contents("$tmp/src/$header/marker", 'original directory');
        [$status, $stdout, $stderr] = run_generator($source);
        if ($status !== 1 || !str_contains($stderr, 'could not rename ')
            || file_get_contents("$tmp/src/$header/marker") !== 'original directory') {
            echo "failure: $header not rejected intact (status $status)\n$stdout$stderr";
        }
        check_sentinels('failure');
        unlink("$tmp/src/$header/marker");
        rmdir("$tmp/src/$header");
        file_put_contents("$tmp/src/$header", 'original header');
    }
    foreach ($headers as $header) {
        file_put_contents("$tmp/src/$header", 'original header');
    }
    // Force an existing-file collision; changing exclusive 'x' to 'w' must fail.
    $collision = str_replace("\$path . '.' . bin2hex(random_bytes(8)) . '.tmp'",
        "\$path . '.tmp'", $source, $replacements);
    if ($replacements !== 1) {
        echo "collision: path selector did not match exactly once\n";
    }
    [$status, $stdout, $stderr] = run_generator($collision);
    if ($status !== 1 || !str_contains($stderr, 'could not create temporary output')
        || file_get_contents("$tmp/src/bmpm_data.h") !== 'original header') {
        echo "collision: original header not preserved (status $status)\n$stdout$stderr";
    }
    check_sentinels('collision');
} finally {
    foreach (glob($tmp . '/src/*') as $file) {
        if (is_dir($file)) {
            unlink($file . '/marker');
            rmdir($file);
        } else {
            unlink($file);
        }
    }
    unlink($tmp . '/scripts/gen_bmpm_data.php');
    rmdir($tmp . '/scripts');
    rmdir($tmp . '/src');
    rmdir($tmp);
}
echo "ok\n";
?>
--EXPECT--
ok
