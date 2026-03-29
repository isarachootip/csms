<?php
/**
 * Customer App REST API — Stations
 * GET /app/api/v1/stations.php               → list all stations
 * GET /app/api/v1/stations.php?id=X          → station detail + chargers + connectors
 * GET /app/api/v1/stations.php?nearby=1&lat=X&lng=Y&radius=10  → nearby
 * POST /app/api/v1/stations.php?action=favorite   → toggle favorite {station_id}
 * GET /app/api/v1/stations.php?action=favorites   → list favorites
 */
require_once __DIR__ . '/_base.php';
api_headers();

$cust   = require_auth();
$custId = (int)$cust['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── FAVORITES ──────────────────────────────────────────
if ($action === 'favorites') {
    $rows = DB::fetchAll(
        "SELECT s.*, 1 AS is_favorite,
                (SELECT COUNT(*) FROM chargers WHERE station_id=s.id AND controller_status='Online') AS online_chargers,
                (SELECT COUNT(*) FROM chargers WHERE station_id=s.id) AS total_chargers,
                (SELECT price_per_kwh FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS price_per_kwh
         FROM stations s
         JOIN customer_favorites cf ON cf.station_id=s.id AND cf.customer_id=?
         WHERE s.status='active'
         ORDER BY cf.created_at DESC",
        [$custId]
    );
    json_ok(['stations' => $rows]);
}

if ($action === 'favorite' && $method === 'POST') {
    $stId = (int)(body()['station_id'] ?? 0);
    if (!$stId) json_err('station_id required');
    $exists = DB::fetchOne("SELECT id FROM customer_favorites WHERE customer_id=? AND station_id=?", [$custId, $stId]);
    if ($exists) {
        DB::execute("DELETE FROM customer_favorites WHERE customer_id=? AND station_id=?", [$custId, $stId]);
        json_ok(['favorited' => false]);
    } else {
        DB::execute("INSERT INTO customer_favorites (customer_id, station_id) VALUES (?,?)", [$custId, $stId]);
        json_ok(['favorited' => true]);
    }
}

// ── STATION DETAIL ─────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $station = DB::fetchOne(
        "SELECT s.*,
                (SELECT price_per_kwh FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS price_per_kwh,
                (SELECT fee_type      FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS fee_type,
                (SELECT AVG(rating)   FROM station_reviews        WHERE station_id=s.id) AS avg_rating,
                (SELECT COUNT(*)      FROM station_reviews        WHERE station_id=s.id) AS review_count,
                EXISTS(SELECT 1 FROM customer_favorites WHERE customer_id=? AND station_id=s.id) AS is_favorite
         FROM stations s WHERE s.id=?",
        [$custId, $id]
    );
    if (!$station) json_err('Station not found', 404);

    $chargers = DB::fetchAll(
        "SELECT ch.*, GROUP_CONCAT(
            JSON_OBJECT(
                'id', co.id, 'connector_number', co.connector_number,
                'connector_type', co.connector_type, 'status', co.status
            )
         ) AS connectors_json
         FROM chargers ch
         LEFT JOIN connectors co ON co.charger_id=ch.id
         WHERE ch.station_id=?
         GROUP BY ch.id
         ORDER BY ch.id",
        [$id]
    );

    foreach ($chargers as &$ch) {
        $ch['connectors'] = $ch['connectors_json']
            ? array_map(fn($j) => json_decode($j, true), explode(',', $ch['connectors_json']))
            : [];
        unset($ch['connectors_json']);
    }

    $reviews = DB::fetchAll(
        "SELECT sr.rating, sr.comment, sr.created_at, c.full_name AS customer_name
         FROM station_reviews sr
         JOIN customers c ON c.id=sr.customer_id
         WHERE sr.station_id=?
         ORDER BY sr.created_at DESC LIMIT 10",
        [$id]
    );

    $station['chargers'] = $chargers;
    $station['reviews']  = $reviews;
    json_ok(['station' => $station]);
}

// ── NEARBY ─────────────────────────────────────────────
$nearby = (int)($_GET['nearby'] ?? 0);
if ($nearby) {
    $lat    = (float)($_GET['lat']    ?? 0);
    $lng    = (float)($_GET['lng']    ?? 0);
    $radius = (float)($_GET['radius'] ?? 20);
    if (!$lat || !$lng) json_err('lat and lng required');

    $stations = DB::fetchAll(
        "SELECT s.*,
                (SELECT price_per_kwh FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS price_per_kwh,
                (SELECT fee_type      FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS fee_type,
                (SELECT COUNT(*) FROM chargers WHERE station_id=s.id AND controller_status='Online') AS online_chargers,
                (SELECT COUNT(*) FROM chargers WHERE station_id=s.id) AS total_chargers,
                (SELECT COUNT(*) FROM connectors co JOIN chargers ch ON ch.id=co.charger_id
                 WHERE ch.station_id=s.id AND co.status='Ready to use') AS available_connectors,
                EXISTS(SELECT 1 FROM customer_favorites WHERE customer_id=? AND station_id=s.id) AS is_favorite,
                (SELECT AVG(rating) FROM station_reviews WHERE station_id=s.id) AS avg_rating,
                (6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(s.latitude)) *
                    COS(RADIANS(s.longitude) - RADIANS(?)) +
                    SIN(RADIANS(?)) * SIN(RADIANS(s.latitude))
                )) AS distance_km
         FROM stations s
         WHERE s.status='active'
           AND s.latitude IS NOT NULL
           AND s.longitude IS NOT NULL
         HAVING distance_km <= ?
         ORDER BY distance_km ASC
         LIMIT 50",
        [$custId, $lat, $lng, $lat, $radius]
    );
    json_ok(['stations' => $stations]);
}

// ── LIST ALL ───────────────────────────────────────────
$stations = DB::fetchAll(
    "SELECT s.*,
            (SELECT price_per_kwh FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS price_per_kwh,
            (SELECT fee_type      FROM service_fee_settings WHERE station_id=s.id AND is_active=1 LIMIT 1) AS fee_type,
            (SELECT COUNT(*) FROM chargers WHERE station_id=s.id AND controller_status='Online') AS online_chargers,
            (SELECT COUNT(*) FROM chargers WHERE station_id=s.id) AS total_chargers,
            (SELECT COUNT(*) FROM connectors co JOIN chargers ch ON ch.id=co.charger_id
             WHERE ch.station_id=s.id AND co.status='Ready to use') AS available_connectors,
            EXISTS(SELECT 1 FROM customer_favorites WHERE customer_id=? AND station_id=s.id) AS is_favorite,
            (SELECT AVG(rating) FROM station_reviews WHERE station_id=s.id) AS avg_rating
     FROM stations s
     WHERE s.status='active'
     ORDER BY s.name ASC",
    [$custId]
);
json_ok(['stations' => $stations]);
