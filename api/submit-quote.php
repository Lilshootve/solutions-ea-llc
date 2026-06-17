<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/validator.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/templates.php';

try {
    $config = sea_load_config();
} catch (Throwable $e) {
    sea_json_response([
        'ok' => false,
        'message' => 'The form is temporarily unavailable. Please call us at 407-639-2669.',
    ], 500);
}

$secret = (string) ($config['recaptcha_secret_key'] ?? '');
if (!sea_is_configured($secret)) {
    sea_json_response([
        'ok' => false,
        'message' => 'Form is not fully configured yet. Please contact us by phone.',
    ], 503);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
  $payload = $_POST;
}

$ip = sea_client_ip();
$rateMessage = sea_check_rate_limit($config, $ip);
if ($rateMessage !== null) {
    sea_json_response(['ok' => false, 'message' => $rateMessage], 429);
}

$recaptchaToken = sea_sanitize_string($payload['recaptchaToken'] ?? '', 2000);
if (!sea_verify_recaptcha($secret, $recaptchaToken, $ip)) {
    sea_json_response([
        'ok' => false,
        'message' => 'Please complete the reCAPTCHA verification and try again.',
    ], 400);
}

$validation = sea_validate_quote_submission($payload);
if (!$validation['valid']) {
    sea_json_response([
        'ok' => false,
        'message' => 'Please review the highlighted fields and try again.',
        'errors' => $validation['errors'],
    ], 422);
}

$data = $validation['data'];
$mailer = new SeaMailer($config);

$internal = sea_build_internal_quote_email($config, $data);
$sentInternal = $mailer->send(
    (string) $config['mail_to'],
    (string) ($config['mail_to_name'] ?? 'SOLUTIONS EA LLC'),
    $internal['subject'],
    $internal['html'],
    $internal['text'],
    $data['email'],
    $data['fullName']
);

if (!$sentInternal) {
    sea_mail_log('Quote form failed: ' . $mailer->getLastError());
    sea_json_response([
        'ok' => false,
        'message' => 'We could not send your request right now. Please call us at 407-639-2669.',
    ], 500);
}

$confirmation = sea_build_confirmation_quote_email($config, $data);
$mailer->send(
    $data['email'],
    $data['fullName'],
    $confirmation['subject'],
    $confirmation['html'],
    $confirmation['text'],
    (string) $config['mail_to'],
    (string) ($config['mail_from_name'] ?? 'SOLUTIONS EA LLC')
);

sea_json_response([
    'ok' => true,
    'message' => 'Your quote request has been submitted successfully.',
]);
