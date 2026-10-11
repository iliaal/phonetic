--TEST--
arginfo guard rejects incomplete or nonliteral BMPM accuracy definitions
--SKIPIF--
<?php
if (!function_exists('proc_open')) {
    die('skip proc_open unavailable');
}
?>
--FILE--
<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/phonetic-arginfo-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/scripts');
mkdir($tmp . '/src');
copy($root . '/scripts/check_arginfo.php', $tmp . '/scripts/check_arginfo.php');
copy($root . '/phonetic.stub.php', $tmp . '/phonetic.stub.php');
copy($root . '/phonetic_arginfo.h', $tmp . '/phonetic_arginfo.h');
$c = file_get_contents($root . '/src/bmpm.c');
$stub = file_get_contents($root . '/phonetic.stub.php');
$arginfo = file_get_contents($root . '/phonetic_arginfo.h');
function check_guard(string $source, int $expected, string $label, ?string $changedStub = null): void {
    global $tmp, $stub, $arginfo;
    $inputStub = $changedStub ?? $stub;
    file_put_contents($tmp . '/phonetic.stub.php', $inputStub);
    file_put_contents($tmp . '/phonetic_arginfo.h', preg_replace(
        '/Stub hash: [0-9a-f]{40}/', 'Stub hash: ' . sha1(str_replace("\r\n", "\n", $inputStub)), $arginfo));
    file_put_contents($tmp . '/src/bmpm.c', $source);
    $process = proc_open([PHP_BINARY, '-n', $tmp . '/scripts/check_arginfo.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expected) {
        echo "$label: expected $expected, got $exit\n$stdout$stderr";
    }
}
try {
    check_guard($c, 0, 'original');
    check_guard(str_replace("\n", "\r\n", $c), 0, 'CRLF');
    foreach (['APPROX' => 10, 'EXACT' => 20] as $name => $value) {
        $pattern = '/^#define PH_BMPM_' . $name . '[^\r\n]*/m';
        $directive = '#define PH_BMPM_' . $name;
        foreach ([
            'expression' => " $value + 1",
            'octal' => " 0$value",
            'hexadecimal' => ' 0x' . dechex($value),
            'suffix' => " {$value}U",
            'identifier' => " {$value}invalid",
            'adjacent line' => "\n$value",
            'blank line' => "\n\n$value",
            'empty' => '',
            'duplicate' => " $value\n$directive $value",
            'duplicate expression' => " $value\n$directive $value + 1",
        ] as $label => $tail) {
            check_guard(preg_replace($pattern, $directive . $tail, $c), 2, "$name $label");
        }
        check_guard(preg_replace($pattern, $directive . ' ' . ($value + 1), $c), 1, "$name mismatch");
        check_guard(preg_replace($pattern, "\t# define\tPH_BMPM_$name\t$value\t", $c), 0, "$name horizontal space");
        check_guard(preg_replace($pattern, '', $c), 2, "$name missing");
        $pattern = '/^const BMPM_' . $name . '[^\r\n]*/m';
        $declaration = 'const BMPM_' . $name;
        foreach ([
            'octal' => " = 0$value;",
            'expression' => " = $value + 1;",
            'adjacent line' => " =\n$value;",
            'blank line' => " =\n\n$value;",
            'duplicate' => " = $value;\n$declaration = $value;",
        ] as $label => $tail) {
            check_guard($c, 2, "stub $name $label", preg_replace($pattern, $declaration . $tail, $stub));
        }
        check_guard($c, 1, "stub $name mismatch", preg_replace($pattern, $declaration . ' = ' . ($value + 1) . ';', $stub));
        check_guard($c, 2, "stub $name missing", preg_replace($pattern, '', $stub));
    }
} finally {
    unlink($tmp . '/src/bmpm.c');
    unlink($tmp . '/scripts/check_arginfo.php');
    unlink($tmp . '/phonetic.stub.php');
    unlink($tmp . '/phonetic_arginfo.h');
    rmdir($tmp . '/src');
    rmdir($tmp . '/scripts');
    rmdir($tmp);
}
echo "ok\n";
?>
--EXPECT--
ok
