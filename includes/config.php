<?php
// ============================================================
// CSMS Configuration — supports ENV variables (Coolify/Docker)
// ============================================================

// ── Environment: read from ENV var, fallback to 'local'
define('APP_ENV', getenv('APP_ENV') ?: 'local');

// ── Database — reads from ENV (Coolify sets these automatically)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'csms');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

define('APP_NAME',  'CSMS');
define('APP_TITLE', 'Charging Station Management System');

// ── App URL — reads from ENV (set in Coolify dashboard)
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/project3/csms');
define('APP_VERSION', '1.0.0');

// Session lifetime (seconds)
define('SESSION_LIFETIME', 3600);

// OTP settings
define('OTP_EXPIRY_MINUTES', 10);

// ── Email SMTP — reads from ENV
define('SMTP_HOST',      getenv('SMTP_HOST')      ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER',      getenv('SMTP_USER')      ?: 'no-reply@csms.local');
define('SMTP_PASS',      getenv('SMTP_PASS')      ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'CSMS System');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error reporting — hide in production
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load i18n helper early (before session start)
require_once __DIR__ . '/i18n.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => APP_ENV === 'production', // HTTPS on production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Init language after session started
i18n_init();
