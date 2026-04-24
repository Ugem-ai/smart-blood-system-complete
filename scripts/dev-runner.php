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

chdir($projectRoot);

$phpBinary = PHP_BINARY;
$npmBinary = $isWindows ? 'npm.cmd' : 'npm';
$concurrentlyBinary = $projectRoot.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'.bin'.DIRECTORY_SEPARATOR.'concurrently'.($isWindows ? '.cmd' : '');

$processes = [
    'server' => buildProcessCommand([$phpBinary, 'artisan', 'serve']),
    'queue' => buildProcessCommand([$phpBinary, 'artisan', 'queue:listen', '--tries=1', '--timeout=0']),
];

if (! $isWindows && extension_loaded('pcntl')) {
    $processes['logs'] = buildProcessCommand([$phpBinary, 'artisan', 'pail', '--timeout=0']);
} else {
    fwrite(STDOUT, "[dev-runner] Skipping Laravel Pail: unsupported on this platform or missing pcntl.\n");
}

$processes['vite'] = buildProcessCommand([$npmBinary, 'run', 'dev']);

$palette = $isWindows
    ? ['#93c5fd', '#fb7185', '#fdba74']
    : ['#93c5fd', '#c4b5fd', '#fb7185', '#fdba74'];

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

fwrite(STDOUT, '[dev-runner] Starting: '.implode(', ', array_keys($processes)).PHP_EOL);

if ($dryRun) {
    fwrite(STDOUT, '[dev-runner] Dry run command: '.$command.PHP_EOL);
    exit(0);
}

$exitCode = passthruCommand($command, $isWindows);

exit($exitCode);