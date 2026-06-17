<?php

declare(strict_types=1);

final class SeaMailer
{
    private array $config;
    private string $lastError = '';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): bool {
        $this->lastError = '';
        $smtp = (array) ($this->config['smtp'] ?? []);
        $fromEmail = (string) ($this->config['mail_from'] ?? '');
        $fromName = (string) ($this->config['mail_from_name'] ?? 'SOLUTIONS EA LLC');

        if (!empty($smtp['enabled']) && sea_is_configured((string) ($smtp['password'] ?? ''))) {
            $username = (string) ($smtp['username'] ?? $fromEmail);
            if (strcasecmp($fromEmail, $username) !== 0) {
                $this->lastError = 'mail_from must match smtp.username on Hostinger.';
                sea_mail_log($this->lastError);
                return false;
            }

            return $this->sendViaSmtp(
                $toEmail,
                $toName,
                $subject,
                $htmlBody,
                $textBody,
                $fromEmail,
                $fromName,
                $replyToEmail,
                $replyToName,
                $smtp
            );
        }

        return $this->sendViaMail(
            $toEmail,
            $subject,
            $htmlBody,
            $textBody,
            $fromEmail,
            $fromName,
            $replyToEmail,
            $replyToName
        );
    }

    private function sendViaMail(
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromEmail,
        string $fromName,
        ?string $replyToEmail,
        ?string $replyToName
    ): bool {
        $boundary = 'sea_' . bin2hex(random_bytes(12));
        $encodedSubject = $this->encodeHeader($subject);
        $fromHeader = $this->formatAddress($fromEmail, $fromName);

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $fromHeader,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        if ($replyToEmail) {
            $headers[] = 'Reply-To: ' . $this->formatAddress(
                $replyToEmail,
                $replyToName ?? $replyToEmail
            );
        }

        $body = $this->buildMultipartBody($boundary, $textBody, $htmlBody);
        $sent = @mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers));

        if (!$sent) {
            $this->lastError = 'PHP mail() failed. Enable SMTP in config.php.';
            sea_mail_log($this->lastError);
        }

        return $sent;
    }

    private function sendViaSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromEmail,
        string $fromName,
        ?string $replyToEmail,
        ?string $replyToName,
        array $smtp
    ): bool {
        $host = (string) ($smtp['host'] ?? 'smtp.hostinger.com');
        $port = (int) ($smtp['port'] ?? 465);
        $secure = strtolower((string) ($smtp['secure'] ?? 'ssl'));
        $username = (string) ($smtp['username'] ?? $fromEmail);
        $password = (string) ($smtp['password'] ?? '');

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            25,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->lastError = "SMTP connection failed ({$errno}): {$errstr}";
            sea_mail_log($this->lastError);
            return false;
        }

        stream_set_timeout($socket, 25);

        if (!$this->smtpExpect($socket, [220], 'greeting')) {
            fclose($socket);
            return false;
        }

        $ehloHost = parse_url((string) ($this->config['site_url'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
        if (!$this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250], 'EHLO')) {
            fclose($socket);
            return false;
        }

        if ($secure === 'tls') {
            if (!$this->smtpCommand($socket, 'STARTTLS', [220], 'STARTTLS')) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'SMTP TLS negotiation failed.';
                sea_mail_log($this->lastError);
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250], 'EHLO after STARTTLS')) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->smtpCommand($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN')
            || !$this->smtpCommand($socket, base64_encode($username), [334], 'SMTP username')
            || !$this->smtpCommand($socket, base64_encode($password), [235], 'SMTP password')) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], 'MAIL FROM')
            || !$this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], 'RCPT TO')
            || !$this->smtpCommand($socket, 'DATA', [354], 'DATA')) {
            fclose($socket);
            return false;
        }

        $boundary = 'sea_' . bin2hex(random_bytes(12));
        $messageLines = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'To: ' . $this->formatAddress($toEmail, $toName),
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@solutionseallc.com>',
            'X-Mailer: SOLUTIONS EA LLC Website',
        ];

        if ($replyToEmail) {
            $messageLines[] = 'Reply-To: ' . $this->formatAddress(
                $replyToEmail,
                $replyToName ?? $replyToEmail
            );
        }

        $messageLines[] = 'Subject: ' . $this->encodeHeader($subject);
        $messageLines[] = 'MIME-Version: 1.0';
        $messageLines[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $messageLines[] = '';
        $messageLines[] = $this->buildMultipartBody($boundary, $textBody, $htmlBody);

        $payload = $this->dotStuff(implode("\r\n", $messageLines)) . "\r\n.";
        fwrite($socket, $payload . "\r\n");

        if (!$this->smtpExpect($socket, [250], 'message body')) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT', [221], 'QUIT');
        fclose($socket);

        sea_mail_log("Email sent to {$toEmail}");
        return true;
    }

    private function dotStuff(string $message): string
    {
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }

    private function buildMultipartBody(string $boundary, string $textBody, string $htmlBody): string
    {
        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $textBody;
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $htmlBody;
        $parts[] = '--' . $boundary . '--';

        return implode("\r\n", $parts);
    }

    private function formatAddress(string $email, string $name): string
    {
        $cleanName = str_replace(['"', "\r", "\n"], '', $name);
        return '"' . $cleanName . '" <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private function smtpCommand($socket, string $command, array $expectedCodes, string $step): bool
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expectedCodes, $step);
    }

    private function smtpExpect($socket, array $expectedCodes, string $step): bool
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            $this->lastError = "SMTP {$step}: empty server response.";
            sea_mail_log($this->lastError);
            return false;
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $this->lastError = "SMTP {$step} failed: " . trim($response);
            sea_mail_log($this->lastError);
            return false;
        }

        return true;
    }
}

function sea_mail_log(string $message): void
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $line = gmdate('Y-m-d H:i:s') . ' UTC | ' . $message . PHP_EOL;
    @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
}
