<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid      = capp_customer_id();
$cust     = capp_customer();
$search   = trim($_GET['q'] ?? '');
$filterConn = $_GET['conn'] ?? '';
$filterPower = $_GET['power'] ?? '';

$where  = ["s.status='active'"];
$params = [];
if ($search) {
    $where[]  = "(s.name LIKE ? OR s.address LIKE ? OR s.location LIKE ?)";
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($filterConn) {
    $where[]  = "EXISTS (SELECT 1 FROM chargers cc JOIN connectors ccn ON ccn.charger_id=cc.id WHERE cc.station_id=s.id AND ccn.connector_type=?)";
    $params[] = $filterConn;
}
if ($filterPower === 'ac') {
    $where[]  = "EXISTS (SELECT 1 FROM chargers cc WHERE cc.station_id=s.id AND cc.max_power_kw<=22)";
} elseif ($filterPower === 'dc') {
    $where[]  = "EXISTS (SELECT 1 FROM chargers cc WHERE cc.station_id=s.id AND cc.max_power_kw>22)";
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stations = DB::fetchAll(
    "SELECT s.*,
            COUNT(DISTINCT c.id)   AS charger_count,
            COUNT(DISTINCT cn.id)  AS total_connectors,
            SUM(cn.status='Ready to use') AS available,
            MAX(c.max_power_kw)    AS max_kw,
            sfs.price_per_kwh, sfs.fee_type,
            sr.avg_rating
     FROM stations s
     LEFT JOIN chargers c   ON c.station_id = s.id
     LEFT JOIN connectors cn ON cn.charger_id = c.id
     LEFT JOIN service_fee_settings sfs ON sfs.station_id=s.id AND sfs.is_active=1
     LEFT JOIN (SELECT station_id, ROUND(AVG(rating),1) AS avg_rating FROM station_reviews GROUP BY station_id) sr ON sr.station_id=s.id
     $whereStr
     GROUP BY s.id ORDER BY s.name",
    $params
);

$custVehicle = DB::fetchOne(
    "SELECT cv.*, ct.connector_type FROM customer_vehicles cv
     LEFT JOIN car_types ct ON ct.id=cv.car_type_id
     WHERE cv.customer_id=? AND cv.is_default=1 LIMIT 1", [$cid]);

capp_head('ค้นหาสถานีชาร์จ');
?>

<div class="page-content">
    <!-- Top Bar -->
    <div class="top-bar px-4 py-3">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-icons-round text-yellow-400">ev_station</span>
            <h1 class="text-base font-bold text-white">สถานีชาร์จ</h1>
            <span class="ml-auto text-xs text-gray-400"><?= count($stations) ?> สถานี</span>
        </div>
        <!-- Search -->
        <form method="GET" class="relative">
            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาสถานี, ที่อยู่..."
                   class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
            <?php if ($filterConn): ?><input type="hidden" name="conn" value="<?= htmlspecialchars($filterConn) ?>"><?php endif; ?>
        </form>
    </div>

    <div class="px-4 pt-3 space-y-3">

        <!-- Filter Chips -->
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-4 px-4" style="scrollbar-width:none">
            <?php
            $connTypes = ['CCS2','CHAdeMO','Type2','Type1'];
            $myConn    = $custVehicle['connector_type'] ?? '';
            ?>
            <?php if ($myConn): ?>
            <a href="?conn=<?= urlencode($myConn) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                      <?= $filterConn === $myConn ? 'bg-yellow-500 border-yellow-500 text-gray-900' : 'border-yellow-500/50 text-yellow-400' ?>">
                🚗 รถของฉัน (<?= $myConn ?>)
            </a>
            <?php endif; ?>
            <a href="?power=dc<?= $search ? '&q='.urlencode($search) : '' ?>"
               class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                      <?= $filterPower === 'dc' ? 'bg-blue-500 border-blue-500 text-white' : 'border-blue-700/50 text-blue-300' ?>">
                ⚡ DC Fast
            </a>
            <a href="?power=ac<?= $search ? '&q='.urlencode($search) : '' ?>"
               class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                      <?= $filterPower === 'ac' ? 'bg-blue-500 border-blue-500 text-white' : 'border-blue-700/50 text-blue-300' ?>">
                🔌 AC
            </a>
            <?php foreach ($connTypes as $ct): ?>
            <a href="?conn=<?= urlencode($ct) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                      <?= $filterConn === $ct ? 'bg-green-500 border-green-500 text-white' : 'border-green-700/50 text-green-400' ?>">
                <?= $ct ?>
            </a>
            <?php endforeach; ?>
            <?php if ($search || $filterConn || $filterPower): ?>
            <a href="stations.php" class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border border-gray-700 text-gray-400">
                ✕ ล้าง
            </a>
            <?php endif; ?>
        </div>

        <!-- Station List -->
        <?php if (empty($stations)): ?>
        <div class="glass rounded-2xl py-16 text-center">
            <span class="material-icons-round text-5xl text-gray-600 block mb-3">search_off</span>
            <p class="text-gray-400">ไม่พบสถานีชาร์จ</p>
        </div>
        <?php endif; ?>

        <?php foreach ($stations as $st): ?>
        <?php
            $avail  = (int)$st['available'];
            $total  = (int)$st['total_connectors'];
            $maxKw  = (float)$st['max_kw'];
            $isFull = ($avail === 0 && $total > 0);
            $isDC   = $maxKw > 22;
        ?>
        <a href="station_detail.php?id=<?= $st['id'] ?>">
            <div class="glass rounded-2xl p-4 card-glow hover:border-blue-500/40 transition slide-up">
                <div class="flex items-start gap-3">
                    <!-- Station Icon -->
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
                         style="background:<?= $isFull ? 'rgba(239,68,68,.1)' : 'rgba(34,197,94,.1)' ?>">
                        <span class="material-icons-round text-2xl <?= $isFull ? 'text-red-400' : 'text-green-400' ?>">ev_station</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-white text-sm leading-tight"><?= htmlspecialchars($st['name']) ?></p>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($st['location'] ?? '') ?></p>
                            </div>
                            <!-- Available badge -->
                            <div class="text-right flex-shrink-0">
                                <?php if ($isFull): ?>
                                <span class="badge-pill bg-red-500/20 text-red-300 border border-red-500/30">เต็ม</span>
                                <?php else: ?>
                                <span class="badge-pill bg-green-500/20 text-green-300 border border-green-500/30">
                                    <?= $avail ?>/<?= $total ?> ว่าง
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tags row -->
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <?php if ($isDC): ?>
                            <span class="connector-badge border-yellow-700/50 text-yellow-300">⚡ DC <?= (int)$maxKw ?> kW</span>
                            <?php else: ?>
                            <span class="connector-badge border-blue-700/50 text-blue-300">🔌 AC <?= (int)$maxKw ?> kW</span>
                            <?php endif; ?>
                            <?php if ($st['fee_type'] === 'Free Charge' || $st['price_per_kwh'] == 0): ?>
                            <span class="connector-badge border-green-700/50 text-green-300">✅ ฟรี</span>
                            <?php else: ?>
                            <span class="connector-badge border-gray-700 text-gray-300">฿<?= number_format((float)$st['price_per_kwh'],2) ?>/kWh</span>
                            <?php endif; ?>
                            <?php if ($myConn): ?>
                            <?php
                            $compat = DB::fetchOne(
                                "SELECT 1 FROM chargers c JOIN connectors cn ON cn.charger_id=c.id
                                 WHERE c.station_id=? AND cn.connector_type=? LIMIT 1",
                                [$st['id'], $myConn]);
                            ?>
                            <?php if ($compat): ?>
                            <span class="connector-badge border-green-700/50 text-green-300">✅ รองรับรถของคุณ</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($st['avg_rating']): ?>
                            <span class="connector-badge border-yellow-700/40 text-yellow-300">⭐ <?= $st['avg_rating'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Progress bar -->
                <?php if ($total > 0): ?>
                <div class="mt-3">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>ว่าง</span>
                        <span><?= $avail ?>/<?= $total ?> หัวชาร์จ</span>
                    </div>
                    <div class="h-1.5 bg-blue-950 rounded-full">
                        <div class="h-full rounded-full transition-all <?= $isFull ? 'bg-red-500' : 'bg-green-500' ?>"
                             style="width:<?= $total > 0 ? round($avail/$total*100) : 0 ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between mt-3">
                    <p class="text-xs text-gray-500 flex items-center gap-1">
                        <span class="material-icons-round text-xs">location_on</span>
                        <?= htmlspecialchars(substr($st['address'] ?? $st['location'] ?? '-', 0, 50)) ?>
                    </p>
                    <span class="text-xs text-blue-400">ดูรายละเอียด →</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php capp_bottom_nav('find'); ?>
<?php capp_foot(); ?>
