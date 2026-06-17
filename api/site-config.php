<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $config = sea_load_config();
    $siteKey = (string) ($config['recaptcha_site_key'] ?? '');

    sea_json_response([
        'ok' => true,
        'recaptchaSiteKey' => sea_is_configured($siteKey) ? $siteKey : '',
    ]);
} catch (Throwable $e) {
    sea_json_response(['ok' => false, 'recaptchaSiteKey' => ''], 500);
}
