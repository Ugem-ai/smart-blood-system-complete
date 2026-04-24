<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$isWindows = DIRECTORY_SEPARATOR === '\\';
$dryRun = in_array('--dry-run', $argv, true);

function buildProcessCommand(array $parts): string
{
    return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $parts));
}

function passthruCommand(string $command, bool $isWindows): int
{
    if ($isWindows) {
        passthru('cmd /d /s /c "'.$command.'"', $exitCode);

        return $exitCode;
    }

    passthru($command, $exitCode);

    return $exitCode;
}

function envFlag(string $name, bool $default = false): bool
{
    $value = getenv($name);

    if ($value === false) {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

chdir($projectRoot);

$phpBinary = PHP_BINARY;
$concurrentlyBinary = $projectRoot.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'.bin'.DIRECTORY_SEPARATOR.'concurrently'.($isWindows ? '.cmd' : '');

$host = getenv('PHONE_TEST_HOST') ?: '127.0.0.1';
$port = getenv('PHONE_TEST_PORT') ?: '8000';
$withQueue = envFlag('PHONE_TEST_WITH_QUEUE', true);

$processes = [
    'server' => buildProcessCommand([$phpBinary, 'artisan', 'serve', '--host='.$host, '--port='.$port]),
];

if ($withQueue) {
    $processes['queue'] = buildProcessCommand([$phpBinary, 'artisan', 'queue:listen', '--tries=1', '--timeout=0']);
}

$palette = ['#93c5fd', '#fb7185', '#fdba74'];

$commandParts = [
    escapeshellarg($concurrentlyBinary),
    '-c',
    escapeshellarg(implode(',', array_slice($palette, 0, count($processes)))),
];

foreach ($processes as $command) {
    $commandParts[] = escapeshellarg($command);
}

$commandParts[] = escapeshellarg('--names='.implode(',', array_keys($processes)));
$commandParts[] = '--kill-others';

$command = implode(' ', $commandParts);

fwrite(STDOUT, '[phone-test] Expecting built assets from npm run build.'.PHP_EOL);
fwrite(STDOUT, '[phone-test] Starting: '.implode(', ', array_keys($processes)).PHP_EOL);
fwrite(STDOUT, '[phone-test] App URL for ngrok target: http://'.$host.':'.$port.PHP_EOL);

if ($dryRun) {
    fwrite(STDOUT, '[phone-test] Dry run command: '.$command.PHP_EOL);
    exit(0);
}

$exitCode = passthruCommand($command, $isWindows);

exit($exitCode);