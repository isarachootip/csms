<?php
/**
 * Customer App REST API — Wallet
 * GET  /app/api/v1/wallet.php             → balance + transactions
 * POST /app/api/v1/wallet.php?action=topup  {amount}
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'topup' && $method === 'POST') {
    $amount = (float)(body()['amount'] ?? 0);
    if ($amount < 50) json_err('ขั้นต่ำเติมเงิน ฿50');
    if ($amount > 10000) json_err('สูงสุดต่อครั้ง ฿10,000');

    // Ensure wallet
    DB::execute("INSERT IGNORE INTO wallet_accounts (customer_id, balance) VALUES (?,0)", [$custId]);
    $wa = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$custId]);

    $newBal = (float)$wa['balance'] + $amount;
    DB::execute("UPDATE wallet_accounts SET balance=? WHERE id=?", [$newBal, $wa['id']]);
    DB::execute(
        "INSERT INTO wallet_transactions (wallet_id, type, amount, balance_after, description)
         VALUES (?, 'topup', ?, ?, 'เติมเงิน Wallet')",
        [$wa['id'], $amount, $newBal]
    );

    // Notification
    DB::execute(
        "INSERT INTO customer_notifications (customer_id, type, title, body, icon)
         VALUES (?, 'wallet', 'เติมเงินสำเร็จ 💰', ?, 'account_balance_wallet')",
        [$custId, "เติมเงิน ฿" . number_format($amount, 2) . " | ยอดคงเหลือ ฿" . number_format($newBal, 2)]
    );

    json_ok([
        'balance'   => $newBal,
        'added'     => $amount,
        'message'   => 'เติมเงินสำเร็จ',
    ]);
}

// GET — balance + history
$wa = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$custId]);
$txns = $wa ? DB::fetchAll(
    "SELECT * FROM wallet_transactions WHERE wallet_id=? ORDER BY created_at DESC LIMIT 50",
    [$wa['id']]
) : [];

json_ok([
    'balance'      => $wa ? (float)$wa['balance'] : 0.0,
    'currency'     => 'THB',
    'transactions' => $txns,
]);
