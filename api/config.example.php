<?php
/**
 * Copy this file to config.php and fill in your values.
 *
 * reCAPTCHA v2 keys: https://www.google.com/recaptcha/admin
 *   - Register solutionseallc.com (and www)
 *   - Choose reCAPTCHA v2 → "I'm not a robot" Checkbox
 *
 * Hostinger email: create noreply@solutionseallc.com in hPanel → Emails
 * SMTP: smtp.hostinger.com, port 465 (SSL) or 587 (TLS)
 */
return [
    'recaptcha_site_key'   => 'YOUR_RECAPTCHA_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',

    'mail_to'        => 'sales@solutionseallc.com',
    'mail_to_name'   => 'SOLUTIONS EA LLC Sales',
    'mail_from'      => 'noreply@solutionseallc.com',
    'mail_from_name' => 'SOLUTIONS EA LLC',

    'site_url'  => 'https://www.solutionseallc.com',
    'logo_url'  => 'https://www.solutionseallc.com/images/logo.png',
    'phone'     => '407-639-2669',

    'smtp' => [
        'enabled'  => true,
        'host'     => 'smtp.hostinger.com',
        'port'     => 465,
        'secure'   => 'ssl',
        'username' => 'noreply@solutionseallc.com',
        'password' => 'YOUR_EMAIL_PASSWORD',
    ],

    'rate_limit_seconds' => 60,
    'rate_limit_per_hour' => 8,
];
