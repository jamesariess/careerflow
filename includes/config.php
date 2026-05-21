<?php
// ============================================================
// CareerFlow – Configuration
// ============================================================

define('CF_VERSION', '1.0.0');
define('CF_ROOT', dirname(__DIR__));
define('CF_UPLOAD_DIR', CF_ROOT . '/uploads/resumes/');
define('CF_UPLOAD_MAX_MB', 5);

// ── Database ─────────────────────────────────────────────────
// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'sql.freedb.tech');
define('DB_NAME', 'freedb_XKQYArv0'); // put your full database name
define('DB_USER', 'u_KlZNhp');
define('DB_PASS', 'ujCtPPyUM27k');
define('DB_CHARSET', 'utf8mb4');

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME', 'cf_session');
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days

// ── App URL (no trailing slash) ───────────────────────────────
// define('APP_URL', 'http://localhost/careerflow');
define('APP_URL', '');

// ── Encryption key for API keys/passwords (change this!) ────
define('APP_SECRET', 'your-unique-secret-key-change-this-in-production-min-32-chars');
