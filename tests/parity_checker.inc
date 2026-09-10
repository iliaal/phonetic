<?php
/* Both CI lanes put extension= last in TEST_PHP_ARGS; reuse its .so/.dll path.
 * Return [exit code, stdout, stderr]. */

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
