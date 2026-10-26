<?php
namespace App\Command {
    // Isolate the terminal process: never read or update a database in this test.
    function proc_open($command, $descriptors, &$pipes, ...$options) {
        if (!in_array('-NoProfile', $command, true) || !str_ends_with(end($command), 'read-password.ps1')) {
            throw new \RuntimeException('Unexpected reader command');
        }
        $pipes[1] = fopen('php://memory', 'r+');
        fwrite($pipes[1], base64_encode(array_shift($GLOBALS['answers'])));
        rewind($pipes[1]);
        return fopen('php://memory', 'r+');
    }
    function proc_close($process) { fclose($process); return $GLOBALS['reader_exit']; }
}
namespace {
    require dirname(__DIR__).'/vendor/autoload.php';
    if (PHP_OS_FAMILY !== 'Windows') { echo "Windows-only test skipped.\n"; exit; }
    $class = new ReflectionClass(App\Command\AccountPasswordCommand::class);
    $command = $class->newInstanceWithoutConstructor();
    $method = $class->getMethod('askPassword');
    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $io = new Symfony\Component\Console\Style\SymfonyStyle(new Symfony\Component\Console\Input\ArrayInput([]), $output);
    $secret = ' '.bin2hex(random_bytes(12)).' ';
    $GLOBALS['reader_exit'] = 0;
    $GLOBALS['answers'] = ['', $secret];
    $question = new Symfony\Component\Console\Question\Question('New password');
    $question->setValidator(static function ($value) {
        if (strlen($value) < 8) { throw new RuntimeException('Too short'); }
        return $value;
    });
    if ($method->invoke($command, $io, $question) !== $secret) { throw new RuntimeException('Validation retry or whitespace preservation failed'); }
    $GLOBALS['answers'] = [$secret];
    $confirmation = new Symfony\Component\Console\Question\Question('Confirm');
    if ($method->invoke($command, $io, $confirmation) !== $secret) { throw new RuntimeException('Confirmation input failed'); }
    $GLOBALS['reader_exit'] = 1;
    $GLOBALS['answers'] = [''];
    try {
        $method->invoke($command, $io, $confirmation);
        throw new LogicException('Reader failure was accepted');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'stdin')) { throw $e; }
    }
    if (str_contains($output->fetch(), $secret)) { throw new RuntimeException('Secret exposed'); }
    echo "PASS: retry validation, preserve whitespace, confirmation, cancellation, no secret output; no database writes.\n";
}
