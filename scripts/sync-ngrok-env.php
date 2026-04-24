<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$envPath = $projectRoot.DIRECTORY_SEPARATOR.'.env';
$ngrokApi = 'http://127.0.0.1:4040/api/tunnels';

if (! file_exists($envPath)) {
    fwrite(STDERR, "[ngrok-sync] .env file not found.\n");
    exit(1);
}

$response = @file_get_contents($ngrokApi);

if ($response === false) {
    fwrite(STDERR, "[ngrok-sync] Unable to reach ngrok API at {$ngrokApi}.\n");
    exit(1);
}

$payload = json_decode($response, true);

if (! is_array($payload) || ! isset($payload['tunnels']) || ! is_array($payload['tunnels'])) {
    fwrite(STDERR, "[ngrok-sync] Unexpected ngrok API response.\n");
    exit(1);
}

$publicUrl = null;

foreach ($payload['tunnels'] as $tunnel) {
    if (($tunnel['proto'] ?? null) !== 'https') {
        continue;
    }

    $publicUrl = $tunnel['public_url'] ?? null;
    if (is_string($publicUrl) && $publicUrl !== '') {
        break;
    }
}

if (! is_string($publicUrl) || $publicUrl === '') {
    fwrite(STDERR, "[ngrok-sync] No HTTPS ngrok tunnel found.\n");
    exit(1);
}

$host = parse_url($publicUrl, PHP_URL_HOST);

if (! is_string($host) || $host === '') {
    fwrite(STDERR, "[ngrok-sync] Failed to parse ngrok host.\n");
    exit(1);
}

$env = file_get_contents($envPath);

if ($env === false) {
    fwrite(STDERR, "[ngrok-sync] Failed to read .env.\n");
    exit(1);
}

function setEnvValue(string $env, string $key, string $value): string
{
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    if (preg_match($pattern, $env) === 1) {
        return (string) preg_replace($pattern, $line, $env, 1);
    }

    return rtrim($env, "\r\n").PHP_EOL.$line.PHP_EOL;
}

$env = setEnvValue($env, 'APP_URL', $publicUrl);
$env = setEnvValue($env, 'SANCTUM_STATEFUL_DOMAINS', $host);
$env = setEnvValue($env, 'SESSION_SECURE_COOKIE', 'true');

if (file_put_contents($envPath, $env) === false) {
    fwrite(STDERR, "[ngrok-sync] Failed to write .env.\n");
    exit(1);
}

fwrite(STDOUT, "[ngrok-sync] Updated APP_URL={$publicUrl}\n");
fwrite(STDOUT, "[ngrok-sync] Updated SANCTUM_STATEFUL_DOMAINS={$host}\n");
fwrite(STDOUT, "[ngrok-sync] Set SESSION_SECURE_COOKIE=true\n");
