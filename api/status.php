<?php
/**
 * API: Real-time charger status polling
 * GET /api/status.php?station_id=X
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$userId    = (int)$_SESSION['user_id'];
$stationId = (int)($_GET['station_id'] ?? 0);

if (!$stationId) {
    echo json_encode(['error' => 'Missing station_id']); exit;
}

$station = DB::fetchOne("SELECT id FROM stations WHERE id=? AND user_id=?", [$stationId, $userId]);
if (!$station) {
    echo json_encode(['error' => 'Not found']); exit;
}

$chargers = DB::fetchAll(
    "SELECT c.id, c.serial_number, c.brand, c.model, c.max_power_kw,
            c.controller_status, c.last_heartbeat,
            cn.id AS connector_id, cn.status AS connector_status,
            t.id AS active_tx_id, t.start_time, t.estimate_amount,
            t.energy_kwh, t.actual_amount, t.price_per_kwh
     FROM chargers c
     LEFT JOIN connectors cn ON cn.charger_id=c.id AND cn.connector_number=1
     LEFT JOIN transactions t ON t.charger_id=c.id AND t.status='Charging'
     WHERE c.station_id=?
     ORDER BY c.id",
    [$stationId]
);

$result = array_map(function($c) {
    $elapsed = null;
    if ($c['start_time']) {
        $elapsed = (int)((time() - strtotime($c['start_time'])) / 60);
    }
    return [
        'id'                => (int)$c['id'],
        'serial_number'     => $c['serial_number'],
        'label'             => $c['brand'] ? $c['brand'].' '.$c['model'] : 'Charger #'.$c['id'],
        'controller_status' => $c['controller_status'],
        'connector_status'  => $c['connector_status'] ?? 'Unavailable',
        'last_heartbeat'    => $c['last_heartbeat'],
        'active_tx_id'      => $c['active_tx_id'] ? (int)$c['active_tx_id'] : null,
        'start_time'        => $c['start_time'],
        'elapsed_minutes'   => $elapsed,
        'energy_kwh'        => (float)$c['energy_kwh'],
        'estimate_amount'   => (float)$c['estimate_amount'],
    ];
}, $chargers);

echo json_encode([
    'timestamp' => date('c'),
    'chargers'  => $result,
]);
