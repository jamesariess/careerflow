<?php
// ============================================================
// CareerFlow – Auth helpers
// ============================================================
require_once __DIR__ . '/db.php';

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrf_token(): string {
    session_init();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function auth_user(): array|false {
    session_init();
    return $_SESSION['user'] ?? false;
}

function require_auth(): void {
    if (!auth_user()) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'theme' => $user['theme'] ?? 'dark',
    ];
}

function logout_user(): void {
    session_init();
    $_SESSION = [];
    session_destroy();
}

function hash_pw(string $pw): string {
    return password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_pw(string $pw, string $hash): bool {
    return password_verify($pw, $hash);
}

function sanitize(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function log_activity(int $userId, string $action, string $desc = '', ?int $appId = null): void {
    DB::run(
        'INSERT INTO activity_logs (user_id, application_id, action, description) VALUES (?,?,?,?)',
        [$userId, $appId, $action, $desc]
    );
}
