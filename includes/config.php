<?php
// ============================================================
// CSMS Configuration
// ============================================================

// ── Environment: 'local' or 'production'
define('APP_ENV', getenv('APP_ENV') ?: 'local');

// ── Database (switch between local and Hostinger)
if (APP_ENV === 'production') {
    // ✏️  Replace with your Hostinger credentials
    define('DB_HOST', 'localhost');          // Hostinger uses 'localhost'
    define('DB_USER', 'u123456_csmsuser');   // ✏️  your DB username
    define('DB_PASS', 'YourStrongPass!');    // ✏️  your DB password
    define('DB_NAME', 'u123456_csms');       // ✏️  your DB name
    define('DB_PORT', 3306);
} else {
    // Local XAMPP
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'csms');
    define('DB_PORT', 3306);
}

define('APP_NAME', 'CSMS');
define('APP_TITLE', 'Charging Station Management System');

// ✏️  Change to your Hostinger domain
define('APP_URL', APP_ENV === 'production'
    ? 'https://yourdomain.com/csms'     // ✏️  your domain
    : 'http://localhost/project3/csms'
);
define('APP_VERSION', '1.0.0');

// Session lifetime (seconds)
define('SESSION_LIFETIME', 3600);

// OTP settings
define('OTP_EXPIRY_MINUTES', 10);

// Email (SMTP) — Hostinger provides free email
define('SMTP_HOST', APP_ENV === 'production' ? 'smtp.hostinger.com' : 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', APP_ENV === 'production' ? 'noreply@yourdomain.com' : 'no-reply@csms.local'); // ✏️
define('SMTP_PASS', APP_ENV === 'production' ? 'YourEmailPass!'        : '');                      // ✏️
define('SMTP_FROM_NAME', 'CSMS System');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error reporting — hide errors in production
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
