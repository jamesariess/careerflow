<?php
// ============================================================
// CareerFlow – Configuration
// ============================================================

define('CF_VERSION', '1.0.0');
define('CF_ROOT', dirname(__DIR__));
define('CF_UPLOAD_DIR', CF_ROOT . '/uploads/resumes/');
define('CF_UPLOAD_MAX_MB', 5);

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'careerflow');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME', 'cf_session');
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days

// ── App URL (no trailing slash) ───────────────────────────────
define('APP_URL', 'http://localhost/careerflow');
