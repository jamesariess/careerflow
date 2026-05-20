<?php
// ============================================================
// CareerFlow – Security Helper
// Encrypts/decrypts sensitive values like API keys and
// App Passwords stored in the database.
//
// Uses AES-256-CBC via OpenSSL.
// The ENCRYPTION_KEY is derived from APP_SECRET in config.php.
// ============================================================

class Security {

    // ── Get encryption key (32 bytes from config secret) ─────────
    private static function key(): string {
        $secret = defined('APP_SECRET') ? APP_SECRET : 'careerflow-default-secret-change-me';
        return hash('sha256', $secret, true); // 32 raw bytes
    }

    // ── Encrypt a string ──────────────────────────────────────────
    public static function encrypt(string $plaintext): string {
        if (!$plaintext) return '';
        $iv         = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) return $plaintext; // fallback: store plain
        return base64_encode($iv . $ciphertext);
    }

    // ── Decrypt a string ──────────────────────────────────────────
    public static function decrypt(string $ciphertext): string {
        if (!$ciphertext) return '';
        $data = base64_decode($ciphertext, true);
        if ($data === false || strlen($data) < 17) return $ciphertext; // not encrypted
        $iv         = substr($data, 0, 16);
        $encrypted  = substr($data, 16);
        $plain      = openssl_decrypt($encrypted, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : $ciphertext;
    }

    // ── Mask a key for display (show only last 6 chars) ───────────
    public static function maskKey(string $key): string {
        if (!$key) return '';
        $len = mb_strlen($key);
        if ($len <= 6) return str_repeat('•', $len);
        return str_repeat('•', max(6, $len - 6)) . mb_substr($key, -6);
    }

    // ── Rate limiting: store attempts in DB ───────────────────────
    // Tracks failed login/reset attempts by IP
    public static function checkRateLimit(string $action, string $identifier, int $maxAttempts = 5, int $windowSeconds = 900): bool {
        try {
            // Clean old records
            DB::run("DELETE FROM rate_limits WHERE action=? AND identifier=? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$action, $identifier, $windowSeconds]);

            $count = (int)(DB::one("SELECT COUNT(*) AS n FROM rate_limits WHERE action=? AND identifier=?",
                [$action, $identifier])['n'] ?? 0);

            return $count < $maxAttempts;
        } catch (Exception $e) {
            return true; // fail open if table doesn't exist yet
        }
    }

    public static function recordAttempt(string $action, string $identifier): void {
        try {
            DB::run("INSERT INTO rate_limits (action, identifier) VALUES (?,?)", [$action, $identifier]);
        } catch (Exception $e) {}
    }

    public static function clearAttempts(string $action, string $identifier): void {
        try {
            DB::run("DELETE FROM rate_limits WHERE action=? AND identifier=?", [$action, $identifier]);
        } catch (Exception $e) {}
    }

    // ── Get client IP ─────────────────────────────────────────────
    public static function clientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    // ── Generate a secure random token ───────────────────────────
    public static function token(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }

    // ── Sanitize output ───────────────────────────────────────────
    public static function e(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
