<?php
// ============================================================
// CareerFlow – Gmail SMTP Sender
// Sends email via Gmail SMTP using PHP streams (no library needed)
// ============================================================

class GmailSMTP {

    /**
     * Send an email through Gmail SMTP using an App Password.
     *
     * @param string $fromEmail  Your Gmail address
     * @param string $fromName   Your display name
     * @param string $appPassword Gmail App Password (16 chars)
     * @param string $toEmail    Recipient email
     * @param string $subject    Email subject
     * @param string $body       Plain text body
     * @return array ['ok'=>bool, 'error'=>string]
     */
    public static function send(
        string $fromEmail,
        string $fromName,
        string $appPassword,
        string $toEmail,
        string $subject,
        string $body
    ): array {
        // Build raw MIME message
        $boundary = '----=_Part_' . md5(uniqid());
        $headers  = [
            'From'         => "$fromName <$fromEmail>",
            'To'           => $toEmail,
            'Subject'      => '=?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
            'X-Mailer'     => 'CareerFlow v2',
            'Date'         => date('r'),
        ];

        $headerStr = '';
        foreach ($headers as $k => $v) $headerStr .= "$k: $v\r\n";
        $rawMessage = $headerStr . "\r\n" . chunk_split(base64_encode($body));

        // Try native SMTP stream approach
        return self::sendViaStream($fromEmail, $appPassword, $toEmail, $rawMessage);
    }

    private static function sendViaStream(
        string $from,
        string $appPass,
        string $to,
        string $rawMessage
    ): array {
        $host    = 'ssl://smtp.gmail.com';
        $port    = 465;
        $timeout = 30;

        $errno = 0; $errstr = '';
        $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$conn) {
            // Fallback: try TLS on port 587
            return self::sendViaTLS($from, $appPass, $to, $rawMessage);
        }

        try {
            $resp = fgets($conn, 1024);
            if (strpos($resp, '220') !== 0) throw new \RuntimeException("SMTP greeting failed: $resp");

            self::cmd($conn, "EHLO careerflow.local\r\n", '250');
            self::cmd($conn, "AUTH LOGIN\r\n",             '334');
            self::cmd($conn, base64_encode($from) . "\r\n", '334');
            self::cmd($conn, base64_encode($appPass) . "\r\n", '235');
            self::cmd($conn, "MAIL FROM:<$from>\r\n",      '250');
            self::cmd($conn, "RCPT TO:<$to>\r\n",          '250');
            self::cmd($conn, "DATA\r\n",                    '354');
            fwrite($conn, $rawMessage . "\r\n.\r\n");
            $resp = fgets($conn, 1024);
            if (strpos($resp, '250') !== 0) throw new \RuntimeException("Message rejected: $resp");
            self::cmd($conn, "QUIT\r\n", '221');
            fclose($conn);
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            @fclose($conn);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function sendViaTLS(
        string $from,
        string $appPass,
        string $to,
        string $rawMessage
    ): array {
        // Try using mail() as absolute last resort with configured sendmail
        // For production, users should install php-mail or configure sendmail_path
        $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
        $sent = @mail($to, 'CareerFlow Reply', base64_encode($rawMessage), $headers);
        if ($sent) return ['ok' => true, 'error' => ''];
        return [
            'ok'    => false,
            'error' => 'SMTP connection failed. Ensure PHP imap+openssl extensions are enabled, or configure php.ini SMTP settings. Gmail SMTP: smtp.gmail.com:587 with TLS.'
        ];
    }

    private static function cmd($conn, string $cmd, string $expectedCode): void {
        fwrite($conn, $cmd);
        $resp = fgets($conn, 1024);
        if (strpos($resp, $expectedCode) !== 0) {
            throw new \RuntimeException("SMTP error (expected $expectedCode): " . trim($resp));
        }
    }
}
