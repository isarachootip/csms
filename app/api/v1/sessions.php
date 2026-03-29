<?php
/**
 * Customer App REST API — Charging Sessions
 * POST /app/api/v1/sessions.php?action=start  {connector_id}
 * GET  /app/api/v1/sessions.php?action=live&id=X
 * POST /app/api/v1/sessions.php?action=stop   {transaction_id}
 * GET  /app/api/v1/sessions.php?action=active
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── START SESSION ─────────────────────────────────────
if ($action === 'start' && $method === 'POST') {
    $connId = (int)(body()['connector_id'] ?? 0);
    if (!$connId) json_err('connector_id required');

    $conn = DB::fetchOne(
        "SELECT co.*, ch.station_id, ch.id AS charger_id, ch.controller_status
         FROM connectors co
         JOIN chargers ch ON ch.id=co.charger_id
         WHERE co.id=?",
        [$connId]
    );
    if (!$conn) json_err('Connector not found', 404);
    if ($conn['status'] !== 'Ready to use') json_err('Connector ไม่พร้อมใช้งาน: ' . $conn['status']);
    if ($conn['controller_status'] !== 'Online') json_err('เครื่องชาร์จ Offline ไม่สามารถเริ่มได้');

    // Check wallet balance
    $wa = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$custId]);
    if (!$wa || $wa['balance'] < 10) json_err('ยอดเงินใน Wallet ไม่เพียงพอ (ขั้นต่ำ ฿10)');

    // Check no active session
    $active = DB::fetchOne(
        "SELECT id FROM transactions WHERE user_id=? AND status='Charging'",
        [$custId]
    );
    if ($active) json_err('มี Session การชาร์จที่ยังดำเนินอยู่');

    // Get fee setting
    $fee = DB::fetchOne(
        "SELECT * FROM service_fee_settings WHERE station_id=? AND is_active=1 LIMIT 1",
        [$conn['station_id']]
    );
    $pricePerKwh = (float)($fee['price_per_kwh'] ?? 4.00);
    $feeType     = $fee['fee_type'] ?? 'kWh-Based';

    // Create transaction
    $txId = DB::insert(
        "INSERT INTO transactions (connector_id, charger_id, station_id, user_id, status,
                                   start_time, fee_type, price_per_kwh, estimate_amount)
         VALUES (?,?,?,?,'Charging', NOW(), ?, ?, ?)",
        [$connId, $conn['charger_id'], $conn['station_id'], $custId, $feeType, $pricePerKwh, 0]
    );

    // Update connector status
    DB::execute("UPDATE connectors SET status='Charging in progress' WHERE id=?", [$connId]);

    json_ok([
        'transaction_id' => $txId,
        'message'        => 'เริ่มชาร์จแล้ว',
        'start_time'     => date('Y-m-d H:i:s'),
        'price_per_kwh'  => $pricePerKwh,
        'fee_type'       => $feeType,
    ], 201);
}

// ── LIVE STATUS ───────────────────────────────────────
if ($action === 'live' && $method === 'GET') {
    $txId = (int)($_GET['id'] ?? 0);
    if (!$txId) json_err('id required');

    $tx = DB::fetchOne(
        "SELECT t.*, s.name AS station_name, s.address,
                ch.serial_number, ch.brand, ch.max_power_kw,
                co.connector_type, co.status AS connector_status
         FROM transactions t
         JOIN stations s ON s.id=t.station_id
         JOIN chargers ch ON ch.id=t.charger_id
         JOIN connectors co ON co.id=t.connector_id
         WHERE t.id=? AND t.user_id=?",
        [$txId, $custId]
    );
    if (!$tx) json_err('Transaction not found', 404);

    // Simulate meter values for demo
    if ($tx['status'] === 'Charging') {
        $elapsedMin = (int)ceil((time() - strtotime($tx['start_time'])) / 60);
        $simKwh     = round($elapsedMin * ($tx['max_power_kw'] ?? 7.4) / 60, 4);
        $simCost    = round($simKwh * (float)$tx['price_per_kwh'], 2);
        $tx['live_energy_kwh'] = $simKwh;
        $tx['live_cost']       = $simCost;
        $tx['elapsed_minutes'] = $elapsedMin;
    }

    // Latest meter value
    $mv = DB::fetchOne(
        "SELECT * FROM meter_values WHERE transaction_id=? ORDER BY recorded_at DESC LIMIT 1",
        [$txId]
    );
    $tx['meter'] = $mv ?: null;

    json_ok(['transaction' => $tx]);
}

// ── STOP SESSION ──────────────────────────────────────
if ($action === 'stop' && $method === 'POST') {
    $txId = (int)(body()['transaction_id'] ?? 0);
    if (!$txId) json_err('transaction_id required');

    $tx = DB::fetchOne(
        "SELECT * FROM transactions WHERE id=? AND user_id=? AND status='Charging'",
        [$txId, $custId]
    );
    if (!$tx) json_err('ไม่พบ Session หรือไม่มีสิทธิ์หยุด', 404);

    $stopTime    = date('Y-m-d H:i:s');
    $elapsedMin  = (int)ceil((time() - strtotime($tx['start_time'])) / 60);
    $simKwh      = round($elapsedMin * 7.4 / 60, 4);
    $actualCost  = round($simKwh * (float)$tx['price_per_kwh'], 2);

    // Update transaction
    DB::execute(
        "UPDATE transactions SET status='Completed', stop_time=?, stop_reason='Local',
                energy_kwh=?, actual_amount=?, duration_minutes=?
         WHERE id=?",
        [$stopTime, $simKwh, $actualCost, $elapsedMin, $txId]
    );

    // Reset connector
    DB::execute("UPDATE connectors SET status='Ready to use' WHERE id=?", [$tx['connector_id']]);

    // Deduct from wallet
    $wa = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$custId]);
    if ($wa) {
        $newBal = max(0, (float)$wa['balance'] - $actualCost);
        DB::execute("UPDATE wallet_accounts SET balance=? WHERE id=?", [$newBal, $wa['id']]);
        DB::execute(
            "INSERT INTO wallet_transactions (wallet_id, type, amount, balance_after, reference_id, description)
             VALUES (?, 'charge', ?, ?, ?, ?)",
            [$wa['id'], $actualCost, $newBal, "TXN-{$txId}", "ชาร์จไฟ #{$txId}"]
        );
    }

    // Notification
    DB::execute(
        "INSERT INTO customer_notifications (customer_id, type, title, body, icon)
         VALUES (?, 'session', 'ชาร์จเสร็จแล้ว ✅', ?, 'electric_bolt')",
        [$custId, "ใช้พลังงาน {$simKwh} kWh | ค่าบริการ ฿{$actualCost} | {$elapsedMin} นาที"]
    );

    json_ok([
        'transaction_id'  => $txId,
        'status'          => 'Completed',
        'energy_kwh'      => $simKwh,
        'actual_amount'   => $actualCost,
        'duration_minutes'=> $elapsedMin,
        'stop_time'       => $stopTime,
        'wallet_balance'  => $wa ? max(0, (float)$wa['balance'] - $actualCost) : 0,
    ]);
}

// ── ACTIVE SESSION ────────────────────────────────────
if ($action === 'active') {
    $tx = DB::fetchOne(
        "SELECT t.*, s.name AS station_name,
                co.connector_type, co.status AS connector_status
         FROM transactions t
         JOIN stations s ON s.id=t.station_id
         JOIN connectors co ON co.id=t.connector_id
         WHERE t.user_id=? AND t.status='Charging'
         ORDER BY t.start_time DESC LIMIT 1",
        [$custId]
    );
    json_ok(['transaction' => $tx ?: null]);
}

json_err('Invalid action', 404);
