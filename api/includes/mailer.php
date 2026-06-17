<?php

declare(strict_types=1);

final class SeaMailer
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
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
        $fromEmail = (string) $this->config['mail_from'];
        $fromName = (string) $this->config['mail_from_name'];
        $smtp = (array) ($this->config['smtp'] ?? []);

        if (!empty($smtp['enabled']) && sea_is_configured((string) ($smtp['password'] ?? ''))) {
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

        return @mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers));
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
        $secure = (string) ($smtp['secure'] ?? 'ssl');
        $username = (string) ($smtp['username'] ?? $fromEmail);
        $password = (string) ($smtp['password'] ?? '');

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 20);

        if (!$this->smtpExpect($socket, [220])) {
            fclose($socket);
            return false;
        }

        $ehloHost = parse_url((string) ($this->config['site_url'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
        if (!$this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250])) {
            fclose($socket);
            return false;
        }

        if ($secure === 'tls') {
            if (!$this->smtpCommand($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250])) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->smtpCommand($socket, 'AUTH LOGIN', [334])
            || !$this->smtpCommand($socket, base64_encode($username), [334])
            || !$this->smtpCommand($socket, base64_encode($password), [235])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250])
            || !$this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251])
            || !$this->smtpCommand($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $boundary = 'sea_' . bin2hex(random_bytes(12));
        $message = [];
        $message[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000';
        $message[] = 'To: ' . $this->formatAddress($toEmail, $toName);
        $message[] = 'From: ' . $this->formatAddress($fromEmail, $fromName);
        if ($replyToEmail) {
            $message[] = 'Reply-To: ' . $this->formatAddress(
                $replyToEmail,
                $replyToName ?? $replyToEmail
            );
        }
        $message[] = 'Subject: ' . $this->encodeHeader($subject);
        $message[] = 'MIME-Version: 1.0';
        $message[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $message[] = '';
        $message[] = $this->buildMultipartBody($boundary, $textBody, $htmlBody);
        $message[] = '.';

        fwrite($socket, implode("\r\n", $message) . "\r\n");
        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
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

    private function smtpCommand($socket, string $command, array $expectedCodes): bool
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expectedCodes);
    }

    private function smtpExpect($socket, array $expectedCodes): bool
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            return false;
        }

        $code = (int) substr($response, 0, 3);
        return in_array($code, $expectedCodes, true);
    }
}
