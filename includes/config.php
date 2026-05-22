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
define('DB_HOST', 'sql310.ezyro.com');
define('DB_NAME', 'ezyro_41992991_careerflow'); // put your full database name
define('DB_USER', 'ezyro_41992991');
define('DB_PASS', 'd58c0a932d');
define('DB_CHARSET', 'utf8mb4');




// define('DB_HOST', 'localhost');
// define('DB_NAME', 'careerflow');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_CHARSET', 'utf8mb4');




// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME', 'cf_session');
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days

// ── App URL (no trailing slash) ───────────────────────────────
// define('APP_URL', 'http://localhost/careerflow');
define('APP_URL', ''); // Adjust this to your actual URL path

// ── Encryption key for API keys/passwords (change this!) ────
define('APP_SECRET', 'your-unique-secret-key-change-this-in-production-min-32-chars');
