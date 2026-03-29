<?php
/**
 * Customer App REST API — Authentication
 * POST /app/api/v1/auth.php?action=login
 * POST /app/api/v1/auth.php?action=register
 * POST /app/api/v1/auth.php?action=logout
 * GET  /app/api/v1/auth.php?action=me
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/db.php';

function json_ok(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate Bearer token → return customer row
function require_auth(): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $h, $m)) json_err('Unauthorized', 401);
    $token = $m[1];
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

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

// ── LOGIN ──────────────────────────────────────────────
if ($action === 'login' && $method === 'POST') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) json_err('กรุณากรอกอีเมลและรหัสผ่าน');

    $user = DB::fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        json_err('อีเมลหรือรหัสผ่านไม่ถูกต้อง', 401);
    }

    // ค้นหา customer record จาก user_id
    $cust = DB::fetchOne("SELECT * FROM customers WHERE user_id = ?", [$user['id']]);

    // กรณี admin login ผ่าน customer app ให้ดึง customer แรกแทน (dev mode)
    if (!$cust && in_array($user['role'], ['admin', 'operator'])) {
        $cust = DB::fetchOne("SELECT * FROM customers WHERE email = ?", [$email]);
    }

    if (!$cust) json_err('ไม่พบข้อมูลลูกค้า กรุณาสมัครสมาชิกก่อน', 403);

    // Generate token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

    // Ensure api_token column exists
    try {
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS api_token VARCHAR(100) DEFAULT NULL");
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS token_expires_at DATETIME DEFAULT NULL");
    } catch (\Throwable $e) { /* already exists */ }

    DB::execute("UPDATE customers SET api_token=?, token_expires_at=? WHERE id=?",
        [$token, $expires, $cust['id']]);

    // Ensure wallet
    DB::execute("INSERT IGNORE INTO wallet_accounts (customer_id, balance) VALUES (?, 0.00)", [$cust['id']]);
    $wa = DB::fetchOne("SELECT balance FROM wallet_accounts WHERE customer_id=?", [$cust['id']]);

    json_ok([
        'token'          => $token,
        'token_expires'  => $expires,
        'customer' => [
            'id'             => $cust['id'],
            'full_name'      => $cust['full_name'],
            'phone'          => $cust['phone'],
            'email'          => $user['email'],
            'avatar_url'     => $cust['avatar_url'] ?? null,
            'license_plate'  => $cust['license_plate'] ?? '',
            'wallet_balance' => (float)($wa['balance'] ?? 0),
        ],
    ]);
}

// ── REGISTER ───────────────────────────────────────────
if ($action === 'register' && $method === 'POST') {
    $fullName = trim($body['full_name'] ?? '');
    $phone    = trim($body['phone'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $plate    = trim($body['license_plate'] ?? '');

    if (!$fullName || !$phone || !$email || !$password) {
        json_err('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('รูปแบบอีเมลไม่ถูกต้อง');
    if (strlen($password) < 8) json_err('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');

    // ตรวจว่า email ซ้ำทั้งใน users และ customers
    $userExists = DB::fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($userExists) json_err('อีเมลนี้ถูกใช้งานแล้ว');

    $custEmailExists = DB::fetchOne("SELECT id FROM customers WHERE email = ?", [$email]);
    if ($custEmailExists) json_err('อีเมลนี้ถูกใช้งานแล้ว');

    $hash  = password_hash($password, PASSWORD_BCRYPT);
    $names = explode(' ', $fullName, 2);
    $first = $names[0];
    $last  = $names[1] ?? '';

    // เพิ่ม role 'customer' ให้ users table (ถ้ายังไม่มี)
    try {
        DB::execute("ALTER TABLE users MODIFY COLUMN role ENUM('admin','operator','viewer','customer') DEFAULT 'customer'");
    } catch (\Throwable $e) { /* ignore if already exists */ }

    // สร้าง user account (role = 'customer')
    $uid = DB::insert(
        "INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role)
         VALUES (?, ?, ?, ?, ?, 1, 'customer')",
        [$first, $last, $phone, $email, $hash]
    );

    // สร้าง customer record
    $custId = DB::insert(
        "INSERT INTO customers (user_id, full_name, phone, email, license_plate, member_since)
         VALUES (?, ?, ?, ?, ?, CURDATE())",
        [$uid, $fullName, $phone, $email, $plate]
    );

    // สร้าง Wallet (ยอดเริ่มต้น 0)
    DB::execute("INSERT IGNORE INTO wallet_accounts (customer_id, balance) VALUES (?, 0.00)", [$custId]);

    // Generate token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
    try {
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS api_token VARCHAR(100) DEFAULT NULL");
        DB::execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS token_expires_at DATETIME DEFAULT NULL");
    } catch (\Throwable $e) { /* already exists */ }
    DB::execute("UPDATE customers SET api_token=?, token_expires_at=? WHERE id=?", [$token, $expires, $custId]);

    // Welcome notification
    DB::execute(
        "INSERT INTO customer_notifications (customer_id, type, title, body, icon)
         VALUES (?, 'system', 'ยินดีต้อนรับสู่ EV Charge! 🎉', 'สมัครสมาชิกสำเร็จแล้ว เริ่มชาร์จรถของคุณได้เลย', 'celebration')",
        [$custId]
    );

    json_ok([
        'token'         => $token,
        'token_expires' => $expires,
        'customer' => [
            'id'             => $custId,
            'full_name'      => $fullName,
            'phone'          => $phone,
            'email'          => $email,
            'avatar_url'     => null,
            'license_plate'  => $plate,
            'wallet_balance' => 0.0,
        ],
    ], 201);
}

// ── LOGOUT ─────────────────────────────────────────────
if ($action === 'logout' && $method === 'POST') {
    $c = require_auth();
    DB::execute("UPDATE customers SET api_token=NULL, token_expires_at=NULL WHERE id=?", [$c['id']]);
    json_ok(['message' => 'Logged out']);
}

// ── ME (profile quick fetch) ───────────────────────────
if ($action === 'me' && $method === 'GET') {
    $c = require_auth();
    json_ok([
        'id'             => $c['id'],
        'full_name'      => $c['full_name'],
        'phone'          => $c['phone'],
        'email'          => $c['email'],
        'avatar_url'     => $c['avatar_url'] ?? null,
        'license_plate'  => $c['license_plate'] ?? '',
        'wallet_balance' => (float)($c['wallet_balance'] ?? 0),
    ]);
}

json_err('Invalid action', 404);
