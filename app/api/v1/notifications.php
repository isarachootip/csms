<?php
/**
 * Customer App REST API — Notifications
 * GET  /app/api/v1/notifications.php          → list
 * POST /app/api/v1/notifications.php?action=read&id=X  → mark read
 * POST /app/api/v1/notifications.php?action=read_all   → mark all read
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'read' && $method === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        DB::execute("UPDATE customer_notifications SET read_at=NOW() WHERE id=? AND customer_id=?", [$id, $custId]);
    }
    json_ok(['message' => 'Marked as read']);
}

if ($action === 'read_all' && $method === 'POST') {
    DB::execute("UPDATE customer_notifications SET read_at=NOW() WHERE customer_id=? AND read_at IS NULL", [$custId]);
    json_ok(['message' => 'All marked as read']);
}

$notifs = DB::fetchAll(
    "SELECT * FROM customer_notifications WHERE customer_id=? ORDER BY created_at DESC LIMIT 50",
    [$custId]
);
$unread = DB::fetchOne(
    "SELECT COUNT(*) AS n FROM customer_notifications WHERE customer_id=? AND read_at IS NULL",
    [$custId]
)['n'] ?? 0;

json_ok(['notifications' => $notifs, 'unread_count' => (int)$unread]);
