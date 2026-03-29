<?php
/**
 * Shared helpers for Customer REST API v1
 */
if (!defined('DB_HOST')) {
    require_once dirname(__DIR__, 3) . '/includes/config.php';
    require_once dirname(__DIR__, 3) . '/includes/db.php';
}

function api_headers(): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function json_ok(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function require_auth(): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $h, $m)) json_err('Unauthorized', 401);
    $token = trim($m[1]);

    // Ensure columns exist (idempotent)
    try {
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS api_token VARCHAR(100) DEFAULT NULL");
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS token_expires_at DATETIME DEFAULT NULL");
    } catch (\Throwable $e) { /* ignore */ }

    $row = DB::fetchOne(
        "SELECT c.*, u.email, u.role, u.is_verified,
                wa.balance AS wallet_balance, wa.id AS wallet_id
         FROM customers c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN wallet_accounts wa ON wa.customer_id = c.id
         WHERE c.api_token = ? AND c.token_expires_at > NOW()",
        [$token]
    );
    if (!$row) json_err('Invalid or expired token', 401);
    return $row;
}

function body(): array {
    static $b = null;
    if ($b === null) $b = json_decode(file_get_contents('php://input'), true) ?? [];
    return $b;
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
