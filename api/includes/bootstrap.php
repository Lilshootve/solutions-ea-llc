<?php

declare(strict_types=1);

function sea_load_config(): array
{
    $path = __DIR__ . '/../config.php';
    if (!is_readable($path)) {
        throw new RuntimeException('Server configuration is missing.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('Invalid server configuration.');
    }

    return $config;
}

function sea_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sea_client_ip(): string
{
    $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($candidates as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $value = trim(explode(',', (string) $_SERVER[$key])[0]);
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return '0.0.0.0';
}

function sea_sanitize_string(?string $value, int $maxLength = 500): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\r\n|\r|\n/', "\n", $value) ?? '';
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function sea_is_configured(string $value): bool
{
    return $value !== '' && !str_starts_with($value, 'YOUR_');
}
