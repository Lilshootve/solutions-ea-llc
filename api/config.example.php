<?php
/**
 * Copy this file to config.php and fill in your values.
 *
 * reCAPTCHA v2 keys: https://www.google.com/recaptcha/admin
 *   - Register solutionseallc.com (and www)
 *   - Choose reCAPTCHA v2 → "I'm not a robot" Checkbox
 *
 * Hostinger email: use an existing mailbox (e.g. sales@solutionseallc.com).
 * IMPORTANT: mail_from and smtp.username MUST be the same email address.
 * SMTP: smtp.hostinger.com — try port 587 + tls if 465 + ssl fails.
 *
 * SMTP test URL (after deploy):
 *   /api/test-smtp.php?token=YOUR_SMTP_TEST_TOKEN
 */
return [
    'recaptcha_site_key'   => 'YOUR_RECAPTCHA_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',

    'mail_to'        => 'sales@solutionseallc.com',
    'mail_to_name'   => 'SOLUTIONS EA LLC Sales',
    'mail_from'      => 'sales@solutionseallc.com',
    'mail_from_name' => 'SOLUTIONS EA LLC',
    'smtp_test_token' => 'YOUR_RANDOM_TEST_TOKEN',

    'site_url'  => 'https://www.solutionseallc.com',
    'logo_url'  => 'https://www.solutionseallc.com/images/logo.png',
    'phone'     => '407-639-2669',

    'smtp' => [
        'enabled'  => true,
        'host'     => 'smtp.hostinger.com',
        'port'     => 465,
        'secure'   => 'ssl',
        'username' => 'sales@solutionseallc.com',
        'password' => 'YOUR_EMAIL_PASSWORD',
    ],

    // If port 465 fails, switch to:
    // 'port' => 587, 'secure' => 'tls',

    'rate_limit_seconds' => 60,
    'rate_limit_per_hour' => 8,
];
