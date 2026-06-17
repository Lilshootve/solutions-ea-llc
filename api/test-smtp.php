<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $config = sea_load_config();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'config.php not found or invalid.']);
    exit;
}

$token = (string) ($_GET['token'] ?? '');
$expected = (string) ($config['smtp_test_token'] ?? '');

if (!sea_is_configured($expected) || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Forbidden. Set smtp_test_token in config.php and pass ?token=...',
    ]);
    exit;
}

$to = (string) ($config['mail_to'] ?? 'sales@solutionseallc.com');
$from = (string) ($config['mail_from'] ?? '');
$smtpUser = (string) ($config['smtp']['username'] ?? '');

$mailer = new SeaMailer($config);
$sent = $mailer->send(
    $to,
    (string) ($config['mail_to_name'] ?? 'SOLUTIONS EA LLC'),
    'SMTP Test — SOLUTIONS EA LLC',
    '<p>If you received this, SMTP is working.</p>',
    'If you received this, SMTP is working.',
    $to,
    'SOLUTIONS EA LLC'
);

echo json_encode([
    'ok' => $sent,
    'to' => $to,
    'from' => $from,
    'smtp_username' => $smtpUser,
    'from_matches_smtp_user' => strcasecmp($from, $smtpUser) === 0,
    'error' => $mailer->getLastError(),
    'hint' => $sent
        ? 'Check inbox and spam for the test email.'
        : 'Fix the error above in api/config.php. On Hostinger, mail_from and smtp.username must be the same address.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
