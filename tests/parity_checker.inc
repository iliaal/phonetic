<?php
/* Runs scripts/oracle/parity/check.php in a child PHP and returns
 * [exit code, stdout, stderr].
 *
 * The built extension is not at a fixed path across runners: POSIX builds put
 * it at modules/phonetic.so, php-windows-builder at build\php_phonetic.dll.
 * Both CI paths export TEST_PHP_ARGS whose extension= directive is the last
 * token, so reuse it and keep modules/phonetic.so as the fallback for direct
 * local runs where TEST_PHP_ARGS is unset. */

function parity_check_run(string $golden, string $words): array
{
    $root = dirname(__DIR__);
    $extension = 'extension=' . $root . '/modules/phonetic.so';
    $testArgs = (string) getenv('TEST_PHP_ARGS');
    $pos = strpos($testArgs, 'extension=');
    if ($pos !== false) {
        $extension = rtrim(substr($testArgs, $pos));
    }

    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, '-n', '-d', $extension, $root . '/scripts/oracle/parity/check.php', $golden, $words],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
    );
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}
