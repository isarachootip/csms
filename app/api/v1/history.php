<?php
/**
 * Customer App REST API — Charging History
 * GET /app/api/v1/history.php          → list
 * GET /app/api/v1/history.php?id=X     → receipt detail
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $tx = DB::fetchOne(
        "SELECT t.*, s.name AS station_name, s.address,
                ch.serial_number, ch.brand, ch.model,
                co.connector_type
         FROM transactions t
         JOIN stations s ON s.id=t.station_id
         JOIN chargers ch ON ch.id=t.charger_id
         JOIN connectors co ON co.id=t.connector_id
         WHERE t.id=? AND t.user_id=?",
        [$id, $custId]
    );
    if (!$tx) json_err('Transaction not found', 404);

    $mv = DB::fetchAll(
        "SELECT power_kw, energy_kwh, voltage, current_a, soc_percent, recorded_at
         FROM meter_values WHERE transaction_id=? ORDER BY recorded_at ASC",
        [$id]
    );
    $tx['meter_values'] = $mv;

    // Check review
    $review = DB::fetchOne("SELECT * FROM station_reviews WHERE transaction_id=?", [$id]);
    $tx['review'] = $review ?: null;

    json_ok(['transaction' => $tx]);
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$total = DB::fetchOne(
    "SELECT COUNT(*) AS n FROM transactions WHERE user_id=? AND status IN ('Completed','Stopped','Faulted')",
    [$custId]
)['n'] ?? 0;

$rows = DB::fetchAll(
    "SELECT t.id, t.start_time, t.stop_time, t.energy_kwh, t.actual_amount,
            t.duration_minutes, t.status, t.fee_type,
            s.name AS station_name, s.address,
            co.connector_type,
            ch.brand AS charger_brand
     FROM transactions t
     JOIN stations s ON s.id=t.station_id
     JOIN connectors co ON co.id=t.connector_id
     JOIN chargers ch ON ch.id=t.charger_id
     WHERE t.user_id=? AND t.status IN ('Completed','Stopped','Faulted')
     ORDER BY t.start_time DESC
     LIMIT $limit OFFSET $offset",
    [$custId]
);

// Totals
$totals = DB::fetchOne(
    "SELECT SUM(energy_kwh) AS total_kwh, SUM(actual_amount) AS total_spent,
            COUNT(*) AS total_sessions
     FROM transactions WHERE user_id=? AND status='Completed'",
    [$custId]
);

json_ok([
    'transactions' => $rows,
    'total'        => (int)$total,
    'page'         => $page,
    'per_page'     => $limit,
    'totals'       => [
        'total_kwh'      => round((float)($totals['total_kwh'] ?? 0), 2),
        'total_spent'    => round((float)($totals['total_spent'] ?? 0), 2),
        'total_sessions' => (int)($totals['total_sessions'] ?? 0),
    ],
]);
