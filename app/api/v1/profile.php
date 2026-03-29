<?php
/**
 * Customer App REST API — Profile
 * GET /app/api/v1/profile.php                  → get profile
 * PUT /app/api/v1/profile.php                  → update profile {full_name, phone, license_plate}
 * GET /app/api/v1/profile.php?action=vehicles  → list vehicles
 * POST/DELETE /app/api/v1/profile.php?action=vehicle  → add/remove vehicle
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── VEHICLES ──────────────────────────────────────────
if ($action === 'vehicles') {
    if ($method === 'POST') {
        $b = body();
        $carTypeId = (int)($b['car_type_id'] ?? 0) ?: null;
        $plate     = trim($b['license_plate'] ?? '');
        $nickname  = trim($b['nickname'] ?? '');
        $isDefault = (int)($b['is_default'] ?? 0);
        if (!$plate) json_err('license_plate required');

        if ($isDefault) {
            DB::execute("UPDATE customer_vehicles SET is_default=0 WHERE customer_id=?", [$custId]);
        }
        $vid = DB::insert(
            "INSERT INTO customer_vehicles (customer_id, car_type_id, license_plate, nickname, is_default)
             VALUES (?,?,?,?,?)",
            [$custId, $carTypeId, $plate, $nickname, $isDefault]
        );
        json_ok(['vehicle_id' => $vid, 'message' => 'เพิ่มรถเรียบร้อย'], 201);
    }

    if ($method === 'DELETE') {
        $vid = (int)(body()['vehicle_id'] ?? 0);
        if (!$vid) json_err('vehicle_id required');
        DB::execute("DELETE FROM customer_vehicles WHERE id=? AND customer_id=?", [$vid, $custId]);
        json_ok(['message' => 'ลบรถเรียบร้อย']);
    }

    $vehicles = DB::fetchAll(
        "SELECT cv.*, ct.name AS car_name, ct.brand AS car_brand,
                ct.connector_type, ct.battery_kwh
         FROM customer_vehicles cv
         LEFT JOIN car_types ct ON ct.id=cv.car_type_id
         WHERE cv.customer_id=?
         ORDER BY cv.is_default DESC, cv.created_at ASC",
        [$custId]
    );
    $carTypes = DB::fetchAll("SELECT id, name, brand, connector_type, battery_kwh FROM car_types ORDER BY brand, name");
    json_ok(['vehicles' => $vehicles, 'car_types' => $carTypes]);
}

// ── UPDATE PROFILE ────────────────────────────────────
if ($method === 'PUT') {
    $b        = body();
    $fullName = trim($b['full_name'] ?? $cust['full_name']);
    $phone    = trim($b['phone']     ?? $cust['phone']);
    $plate    = trim($b['license_plate'] ?? $cust['license_plate'] ?? '');

    DB::execute(
        "UPDATE customers SET full_name=?, phone=?, license_plate=? WHERE id=?",
        [$fullName, $phone, $plate, $custId]
    );

    // Sync user name
    $names = explode(' ', $fullName, 2);
    DB::execute(
        "UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?",
        [$names[0], $names[1] ?? '', $phone, $cust['user_id']]
    );

    json_ok(['message' => 'อัปเดตโปรไฟล์แล้ว']);
}

// ── GET PROFILE ───────────────────────────────────────
$profile = DB::fetchOne(
    "SELECT c.*, u.email, u.role,
            ct.name AS car_name, ct.brand AS car_brand, ct.connector_type, ct.battery_kwh,
            wa.balance AS wallet_balance,
            (SELECT COUNT(*) FROM transactions WHERE user_id=c.user_id AND status='Completed') AS total_sessions,
            (SELECT SUM(energy_kwh) FROM transactions WHERE user_id=c.user_id AND status='Completed') AS total_kwh,
            (SELECT SUM(actual_amount) FROM transactions WHERE user_id=c.user_id AND status='Completed') AS total_spent,
            (SELECT COUNT(*) FROM customer_notifications WHERE customer_id=c.id AND read_at IS NULL) AS unread_notifs
     FROM customers c
     JOIN users u ON u.id=c.user_id
     LEFT JOIN car_types ct ON ct.id=c.car_type_id
     LEFT JOIN wallet_accounts wa ON wa.customer_id=c.id
     WHERE c.id=?",
    [$custId]
);
if (!$profile) json_err('Profile not found', 404);

// Remove sensitive fields
unset($profile['api_token'], $profile['token_expires_at']);
$profile['total_kwh']   = round((float)($profile['total_kwh']   ?? 0), 2);
$profile['total_spent'] = round((float)($profile['total_spent'] ?? 0), 2);

json_ok(['profile' => $profile]);
