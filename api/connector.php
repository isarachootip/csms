<?php
/**
 * API: Update connector status (simulate OCPP webhook)
 * POST /api/connector.php
 * Body: { connector_id, status }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$connectorId = (int)($data['connector_id'] ?? 0);
$status      = $data['status'] ?? '';

$allowed = ['Ready to use','Plugged in','Charging in progress',
            'Charging paused by vehicle','Charging paused by charger',
            'Charging finish','Unavailable'];

if (!$connectorId || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']); exit;
}

DB::execute("UPDATE connectors SET status=?,updated_at=NOW() WHERE id=?", [$status, $connectorId]);
echo json_encode(['success' => true, 'connector_id' => $connectorId, 'status' => $status]);
