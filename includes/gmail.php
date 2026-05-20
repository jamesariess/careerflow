<?php
// ============================================================
// CareerFlow – Gmail IMAP Helper
// ============================================================
// Uses PHP's built-in IMAP extension with Gmail App Passwords.
//
// Setup for users:
//  1. Enable 2-Factor Authentication on their Google account
//  2. Go to myaccount.google.com/apppasswords
//  3. Create an App Password for "Mail" / "Other (CareerFlow)"
//  4. Enter their Gmail address + that 16-char App Password here
//
// The IMAP extension must be enabled in php.ini:
//   extension=imap
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai.php';

class GmailSync {
    private $imap  = null;
    private int    $userId;
    private array  $userRow;
    private string $address;
    private string $appPassword;

    private const MAILBOX = '{imap.gmail.com:993/imap/ssl}INBOX';

    public function __construct(int $userId) {
        $this->userId = $userId;
        $row = DB::one('SELECT * FROM users WHERE id=?', [$userId]);
        if (!$row) throw new RuntimeException('User not found.');
        $this->userRow     = $row;
        $this->address     = trim($row['gmail_address']   ?? '');
        $this->appPassword = trim($row['gmail_app_password'] ?? '');
        if (!$this->address || !$this->appPassword) {
            throw new RuntimeException('Gmail credentials not configured. Add them in Settings → AI & Integrations.');
        }
        if (!function_exists('imap_open')) {
            throw new RuntimeException('PHP IMAP extension is not installed. Run: sudo apt install php-imap and restart Apache.');
        }
    }

    // ── Connect to Gmail via IMAP ───────────────────────────────────
    private function connect(): void {
        $this->imap = @imap_open(
            self::MAILBOX,
            $this->address,
            $this->appPassword,
            0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );
        if (!$this->imap) {
            $err = imap_last_error();
            throw new RuntimeException("Could not connect to Gmail: $err. Check your App Password in Settings.");
        }
    }

    // ── Sync recent emails (last N days) ────────────────────────────
    public function sync(int $days = 7, int $limit = 50): array {
        $this->connect();

        $since    = date('d-M-Y', strtotime("-{$days} days"));
        $uids     = imap_search($this->imap, "SINCE \"$since\"", SE_UID);
        $results  = ['synced' => 0, 'job_related' => 0, 'errors' => []];

        if (!$uids) {
            imap_close($this->imap);
            return $results;
        }

        // Newest first, cap at limit
        rsort($uids);
        $uids = array_slice($uids, 0, $limit);

        // Load user's applications for matching
        $applications = DB::all(
            "SELECT id, company, job_title, recruiter_email FROM applications WHERE user_id=? AND status NOT IN ('Hired','Rejected')",
            [$this->userId]
        );

        foreach ($uids as $uid) {
            try {
                $msgId = 'gmail_' . $uid;
                // Skip already synced
                $exists = DB::one('SELECT id FROM gmail_emails WHERE user_id=? AND gmail_msg_id=?', [$this->userId, $msgId]);
                if ($exists) continue;

                $header  = imap_fetchheader($this->imap, $uid, FT_UID);
                $overview = imap_fetch_overview($this->imap, $uid, FT_UID);
                if (empty($overview[0])) continue;
                $ov = $overview[0];

                $subject     = self::decodeHeader($ov->subject ?? '(no subject)');
                $senderRaw   = self::decodeHeader($ov->from ?? '');
                $receivedAt  = date('Y-m-d H:i:s', strtotime($ov->date ?? 'now'));
                [$senderName, $senderEmail] = self::parseSender($senderRaw);

                // Get body
                $bodyPlain = self::getBody($this->imap, $uid);

                // AI analysis
                $analysis = AI::analyzeEmail(
                    $subject,
                    mb_substr($bodyPlain, 0, 3000),
                    $this->userId,
                    $applications
                );

                // Try to match to an application
                $appId = null;
                if ($analysis['matched_company']) {
                    foreach ($applications as $a) {
                        if (stripos($a['company'], $analysis['matched_company']) !== false
                         || stripos($analysis['matched_company'], $a['company']) !== false) {
                            $appId = $a['id'];
                            break;
                        }
                    }
                }
                // Fallback: match by recruiter email
                if (!$appId && $senderEmail) {
                    foreach ($applications as $a) {
                        if ($a['recruiter_email'] && strtolower($a['recruiter_email']) === strtolower($senderEmail)) {
                            $appId = $a['id'];
                            break;
                        }
                    }
                }

                $isJobRelated = (bool)($analysis['is_job_related'] ?? false);

                // Insert email record
                DB::run("INSERT INTO gmail_emails
                    (user_id, application_id, gmail_msg_id, subject, sender_name, sender_email,
                     body_plain, received_at, is_job_related, ai_category, ai_sentiment, ai_summary, is_read)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $this->userId, $appId, $msgId, $subject, $senderName, $senderEmail,
                        mb_substr($bodyPlain, 0, 60000), $receivedAt,
                        $isJobRelated ? 1 : 0,
                        $analysis['category']  ?? 'other',
                        $analysis['sentiment'] ?? 'neutral',
                        $analysis['summary']   ?? '',
                        isset($ov->seen) && $ov->seen ? 1 : 0,
                    ]
                );

                // Auto-update application status if suggested
                if ($appId && !empty($analysis['suggested_status'])) {
                    DB::run('UPDATE applications SET status=? WHERE id=? AND user_id=?',
                        [$analysis['suggested_status'], $appId, $this->userId]);
                    log_activity($this->userId, 'gmail_status_update',
                        "Status auto-updated to {$analysis['suggested_status']} based on email: $subject", $appId);
                }

                // Create a notification for job-related emails
                if ($isJobRelated) {
                    DB::run("INSERT INTO notifications (user_id, title, body, type, link) VALUES (?,?,?,?,?)",
                        [
                            $this->userId,
                            "📧 " . ucfirst(str_replace('_',' ', $analysis['category'] ?? 'email')) . " from " . ($senderName ?: $senderEmail),
                            $analysis['summary'] ?? $subject,
                            $analysis['sentiment'] === 'positive' ? 'success' : ($analysis['sentiment'] === 'negative' ? 'warning' : 'info'),
                            APP_URL . '/pages/gmail.php',
                        ]
                    );
                    $results['job_related']++;
                }

                $results['synced']++;
            } catch (Exception $e) {
                $results['errors'][] = "UID $uid: " . $e->getMessage();
            }
        }

        DB::run('UPDATE users SET gmail_sync_at=NOW() WHERE id=?', [$this->userId]);
        imap_close($this->imap);
        return $results;
    }

    // ── Send a reply via SMTP (Gmail SMTP) ──────────────────────────
    public static function sendReply(
        int    $userId,
        int    $emailId,
        string $replyBody
    ): bool {
        $row = DB::one('SELECT * FROM gmail_emails WHERE id=? AND user_id=?', [$emailId, $userId]);
        if (!$row) throw new RuntimeException('Email not found.');

        $userRow  = DB::one('SELECT * FROM users WHERE id=?', [$userId]);
        $gmail    = $userRow['gmail_address']      ?? '';
        $appPass  = $userRow['gmail_app_password'] ?? '';
        $fromName = $userRow['name']               ?? '';

        if (!$gmail || !$appPass) throw new RuntimeException('Gmail credentials not configured.');

        $to      = $row['sender_email'];
        $subject = 'Re: ' . $row['subject'];

        // Build raw email
        $boundary = md5(uniqid());
        $headers  = implode("\r\n", [
            "From: $fromName <$gmail>",
            "To: $to",
            "Subject: $subject",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "X-Mailer: CareerFlow",
        ]);
        $message = $headers . "\r\n\r\n" . $replyBody;

        // Use PHP mail() with SMTP settings, or fall back to sendmail
        // For production, configure php.ini SMTP settings or use a mailer library
        $sent = @mail($to, $subject, $replyBody, $headers);

        if ($sent) {
            DB::run('UPDATE gmail_emails SET reply_sent=1, reply_body=?, reply_sent_at=NOW() WHERE id=?',
                [mb_substr($replyBody, 0, 4000), $emailId]);
            log_activity($userId, 'email_reply_sent', "Replied to: {$row['subject']}");
        }
        return $sent;
    }

    // ── Helpers ─────────────────────────────────────────────────────
    private static function decodeHeader(string $str): string {
        if (!$str) return '';
        $decoded = imap_mime_header_decode($str);
        $out = '';
        foreach ($decoded as $part) {
            $charset = $part->charset ?? 'UTF-8';
            $text    = $part->text    ?? '';
            if (strtolower($charset) !== 'default' && strtolower($charset) !== 'utf-8') {
                $text = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $text);
            }
            $out .= $text;
        }
        return trim($out);
    }

    private static function parseSender(string $raw): array {
        // "Name <email>" or just "email"
        if (preg_match('/^(.*?)\s*<([^>]+)>/', $raw, $m)) {
            return [trim($m[1], ' "\''), strtolower(trim($m[2]))];
        }
        return ['', strtolower(trim($raw))];
    }

    private static function getBody($imap, int $uid): string {
        $structure = imap_fetchstructure($imap, $uid, FT_UID);
        $body = '';

        // Multipart
        if (isset($structure->parts) && count($structure->parts)) {
            foreach ($structure->parts as $i => $part) {
                $subtype = strtoupper($part->subtype ?? '');
                if ($subtype === 'PLAIN' || ($subtype === 'HTML' && !$body)) {
                    $raw = imap_fetchbody($imap, $uid, $i + 1, FT_UID);
                    $enc = $part->encoding ?? 0;
                    $raw = self::decodePart($raw, $enc);
                    $charset = 'UTF-8';
                    if (!empty($part->parameters)) {
                        foreach ($part->parameters as $p) {
                            if (strtolower($p->attribute) === 'charset') { $charset = $p->value; break; }
                        }
                    }
                    if (strtolower($charset) !== 'utf-8') {
                        $raw = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $raw) ?: $raw;
                    }
                    if ($subtype === 'PLAIN') {
                        $body = $raw; break;
                    } elseif ($subtype === 'HTML') {
                        $body = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $raw));
                    }
                }
            }
        } else {
            // Single part
            $raw = imap_body($imap, $uid, FT_UID);
            $enc = $structure->encoding ?? 0;
            $body = self::decodePart($raw, $enc);
        }

        return trim(preg_replace('/\n{3,}/', "\n\n", $body));
    }

    private static function decodePart(string $data, int $encoding): string {
        return match($encoding) {
            3 => base64_decode($data),
            4 => quoted_printable_decode($data),
            default => $data,
        };
    }
}
