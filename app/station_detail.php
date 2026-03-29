<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid = capp_customer_id();
$sid = (int)($_GET['id'] ?? 0);
if (!$sid) { header('Location: stations.php'); exit; }

$station = DB::fetchOne("SELECT * FROM stations WHERE id=?", [$sid]);
if (!$station) { header('Location: stations.php'); exit; }

$fee = DB::fetchOne("SELECT * FROM service_fee_settings WHERE station_id=? AND is_active=1 LIMIT 1", [$sid]);

$chargers = DB::fetchAll(
    "SELECT c.*, cn.id AS conn_id, cn.connector_number, cn.connector_type, cn.status AS conn_status,
            t.id AS active_tx, t.start_time AS tx_start, t.energy_kwh AS tx_kwh
     FROM chargers c
     LEFT JOIN connectors cn ON cn.charger_id=c.id
     LEFT JOIN transactions t ON t.charger_id=c.id AND t.status='Charging'
     WHERE c.station_id=? ORDER BY c.id, cn.connector_number",
    [$sid]
);

// Group connectors under charger
$chgMap = [];
foreach ($chargers as $row) {
    $cid2 = $row['id'];
    if (!isset($chgMap[$cid2])) {
        $chgMap[$cid2] = $row;
        $chgMap[$cid2]['connectors'] = [];
    }
    if ($row['conn_id']) {
        $chgMap[$cid2]['connectors'][] = [
            'id'             => $row['conn_id'],
            'connector_type' => $row['connector_type'],
            'status'         => $row['conn_status'],
            'active_tx'      => $row['active_tx'],
            'tx_start'       => $row['tx_start'],
            'tx_kwh'         => $row['tx_kwh'],
        ];
    }
}

$totalConn  = array_sum(array_map(fn($c) => count($c['connectors']), $chgMap));
$availConn  = 0;
foreach ($chgMap as $c) {
    foreach ($c['connectors'] as $cn) {
        if ($cn['status'] === 'Ready to use') $availConn++;
    }
}

$reviews = DB::fetchAll(
    "SELECT sr.*, cu.full_name FROM station_reviews sr
     JOIN customers cu ON cu.id=sr.customer_id
     WHERE sr.station_id=? ORDER BY sr.created_at DESC LIMIT 5",
    [$sid]
);
$avgRating = DB::fetchOne("SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt FROM station_reviews WHERE station_id=?", [$sid]);

// Favorite check
$isFav = DB::fetchOne("SELECT id FROM customer_favorites WHERE customer_id=? AND station_id=?", [capp_customer_id(), $sid]);

// Toggle Favorite
if (isset($_POST['toggle_fav'])) {
    if ($isFav) {
        DB::execute("DELETE FROM customer_favorites WHERE customer_id=? AND station_id=?", [capp_customer_id(), $sid]);
    } else {
        DB::insert("INSERT IGNORE INTO customer_favorites (customer_id,station_id) VALUES (?,?)", [capp_customer_id(), $sid]);
    }
    header("Location: station_detail.php?id=$sid"); exit;
}

// Submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    $txCheck = DB::fetchOne("SELECT id FROM transactions WHERE customer_id=? AND station_id=? AND status='Completed' LIMIT 1",
        [capp_customer_id(), $sid]);
    if ($txCheck) {
        DB::insert("INSERT IGNORE INTO station_reviews (station_id,customer_id,transaction_id,rating,comment) VALUES (?,?,?,?,?)",
            [$sid, capp_customer_id(), $txCheck['id'], $rating, $comment]);
    }
    header("Location: station_detail.php?id=$sid#reviews"); exit;
}

$customerVehicle = DB::fetchOne(
    "SELECT cv.*, ct.connector_type FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id
     WHERE cv.customer_id=? AND cv.is_default=1 LIMIT 1", [capp_customer_id()]);

capp_head(htmlspecialchars($station['name']));
?>

<div class="page-content">
    <?php
    $favBtn = "<form method='POST' style='display:inline'>
        <input type='hidden' name='toggle_fav' value='1'>
        <button type='submit' class='w-9 h-9 flex items-center justify-center rounded-full hover:bg-blue-800/50 transition'>
            <span class='material-icons-round text-lg " . ($isFav ? 'text-red-400' : 'text-gray-400') . "'>" . ($isFav ? 'favorite' : 'favorite_border') . "</span>
        </button>
    </form>";
    capp_top_bar(htmlspecialchars($station['name']), true, $favBtn);
    ?>

    <div class="px-4 pt-3 space-y-4">

        <!-- Station Hero -->
        <div class="glass rounded-2xl p-5 card-glow">
            <div class="flex items-start gap-3">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0"
                     style="background:linear-gradient(135deg,rgba(59,130,246,.3),rgba(30,58,138,.4))">
                    <span class="material-icons-round text-blue-300 text-3xl">ev_station</span>
                </div>
                <div class="flex-1">
                    <h2 class="font-bold text-white text-base"><?= htmlspecialchars($station['name']) ?></h2>
                    <?php if ($station['location']): ?>
                    <p class="text-sm text-blue-300 mt-0.5"><?= htmlspecialchars($station['location']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1 leading-relaxed"><?= htmlspecialchars($station['address'] ?? '') ?></p>
                    <?php if ($avgRating['cnt'] > 0): ?>
                    <div class="flex items-center gap-1 mt-1.5">
                        <?php for ($i=1;$i<=5;$i++) echo $i<=$avgRating['avg']?'⭐':'☆'; ?>
                        <span class="text-xs text-gray-400"><?= $avgRating['avg'] ?> (<?= $avgRating['cnt'] ?> รีวิว)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                <div class="bg-blue-900/40 rounded-xl py-3">
                    <p class="text-xl font-bold <?= $availConn > 0 ? 'text-green-300' : 'text-red-300' ?>"><?= $availConn ?>/<?= $totalConn ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">หัวชาร์จว่าง</p>
                </div>
                <div class="bg-yellow-900/30 rounded-xl py-3">
                    <p class="text-xl font-bold text-yellow-300">
                        <?= $fee ? ($fee['fee_type']==='Free Charge'?'ฟรี':'฿'.number_format((float)$fee['price_per_kwh'],2)) : '-' ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">ต่อ kWh</p>
                </div>
                <div class="bg-purple-900/30 rounded-xl py-3">
                    <p class="text-xl font-bold text-purple-300"><?= count($chgMap) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">เครื่องชาร์จ</p>
                </div>
            </div>

            <!-- Navigate button -->
            <?php if ($station['latitude'] && $station['longitude']): ?>
            <a href="https://maps.google.com/?q=<?= $station['latitude'] ?>,<?= $station['longitude'] ?>" target="_blank"
               class="btn-blue flex items-center justify-center gap-2 w-full py-3 rounded-xl mt-4 text-sm">
                <span class="material-icons-round text-base">directions</span>
                นำทางไปสถานีนี้
            </a>
            <?php endif; ?>
        </div>

        <!-- Charger Grid -->
        <div>
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-1">
                <span class="material-icons-round text-blue-400 text-base">cable</span>
                เลือกหัวชาร์จ
            </p>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ($chgMap as $charger): ?>
                <?php foreach ($charger['connectors'] as $conn): ?>
                <?php
                    $isAvail   = $conn['status'] === 'Ready to use';
                    $isCharging = $conn['status'] === 'Charging in progress';
                    $isMyConn  = $customerVehicle && $customerVehicle['connector_type'] === $conn['connector_type'];
                    $bgCls     = $isAvail ? 'border-green-500/40' : ($isCharging ? 'border-blue-500/40' : 'border-gray-700/40');
                ?>
                <?php if ($isAvail): ?>
                <a href="charge.php?connector_id=<?= $conn['id'] ?>&station_id=<?= $sid ?>">
                <?php else: ?>
                <div>
                <?php endif; ?>
                    <div class="glass rounded-xl p-3 <?= $bgCls ?> transition <?= $isAvail ? 'hover:border-green-400/60 cursor-pointer' : 'opacity-70' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-lg"><?= connector_icon($conn['connector_type']) ?></span>
                            <span class="w-2.5 h-2.5 rounded-full <?= status_color_class($conn['status']) ?>"></span>
                        </div>
                        <p class="text-xs font-bold text-white"><?= $conn['connector_type'] ?></p>
                        <p class="text-xs text-gray-400"><?= (int)$charger['max_power_kw'] ?> kW</p>
                        <p class="text-xs mt-1 <?= $isAvail ? 'text-green-300' : ($isCharging ? 'text-blue-300' : 'text-gray-500') ?>">
                            <?= match($conn['status']) {
                                'Ready to use'         => '✅ ว่างอยู่',
                                'Charging in progress' => '⚡ กำลังชาร์จ',
                                'Plugged in'           => '🔌 เสียบปลั๊กแล้ว',
                                'Unavailable'          => '🔴 ไม่พร้อมใช้งาน',
                                'Charging finish'      => '✔ ชาร์จเสร็จ',
                                default                => $conn['status'],
                            } ?>
                        </p>
                        <?php if ($isMyConn): ?>
                        <span class="text-xs text-yellow-400 font-semibold">✓ รองรับรถคุณ</span>
                        <?php endif; ?>
                        <?php if ($isAvail): ?>
                        <div class="btn-green text-center rounded-lg py-1 mt-2 text-xs font-bold">ชาร์จเลย</div>
                        <?php endif; ?>
                        <?php if ($isCharging && $conn['tx_start']): ?>
                        <p class="text-xs text-blue-300 mt-1">
                            ⏱ <?= (int)((time()-strtotime($conn['tx_start']))/60) ?> นาที
                        </p>
                        <?php endif; ?>
                    </div>
                <?php if ($isAvail): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Fee Details -->
        <?php if ($fee): ?>
        <div class="glass rounded-2xl p-4">
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-1">
                <span class="material-icons-round text-yellow-400 text-base">payments</span>
                ค่าบริการ
            </p>
            <?php if ($fee['fee_type'] === 'Free Charge'): ?>
            <div class="flex items-center gap-2 text-green-300">
                <span class="material-icons-round">check_circle</span>
                <p class="font-bold">ชาร์จฟรี! ไม่มีค่าบริการ</p>
            </div>
            <?php elseif ($fee['fee_type'] === 'kWh-Based'): ?>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-blue-900/40 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400">ราคาต่อ kWh</p>
                    <p class="text-2xl font-bold text-yellow-300">฿<?= number_format((float)$fee['price_per_kwh'],2) ?></p>
                </div>
                <div class="bg-blue-900/40 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400">สกุลเงิน</p>
                    <p class="text-2xl font-bold text-white"><?= $fee['currency'] ?></p>
                </div>
            </div>
            <!-- Cost calculator -->
            <div class="mt-3 p-3 rounded-xl bg-blue-950/50">
                <p class="text-xs text-gray-400 mb-2">คำนวณค่าใช้จ่ายโดยประมาณ</p>
                <div class="flex items-center gap-2">
                    <input type="number" id="calcKwh" placeholder="kWh" min="1" max="200" value="30"
                           class="input-field flex-1 px-3 py-2 rounded-xl text-sm" oninput="calcCost()">
                    <span class="text-gray-400 text-sm">kWh</span>
                    <span class="text-yellow-400 font-bold" id="calcResult">
                        = ฿<?= number_format(30*(float)$fee['price_per_kwh'],2) ?>
                    </span>
                </div>
            </div>
            <script>
            const pricePerKwh = <?= (float)$fee['price_per_kwh'] ?>;
            function calcCost() {
                const kwh = parseFloat(document.getElementById('calcKwh').value) || 0;
                document.getElementById('calcResult').textContent = '= ฿' + (kwh*pricePerKwh).toFixed(2);
            }
            </script>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Reviews -->
        <div id="reviews">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-white flex items-center gap-1">
                    <span class="material-icons-round text-yellow-400 text-base">star</span>
                    รีวิวจากผู้ใช้
                    <?php if ($avgRating['cnt'] > 0): ?>
                    <span class="text-gray-400 text-xs font-normal">(<?= $avgRating['cnt'] ?>)</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Add Review Form -->
            <?php
            $hasCharged = DB::fetchOne("SELECT id FROM transactions WHERE customer_id=? AND station_id=? AND status='Completed'",
                [capp_customer_id(), $sid]);
            $hasReviewed = DB::fetchOne("SELECT id FROM station_reviews WHERE customer_id=? AND station_id=?",
                [capp_customer_id(), $sid]);
            ?>
            <?php if ($hasCharged && !$hasReviewed): ?>
            <div class="glass rounded-xl p-4 mb-3">
                <p class="text-sm font-semibold text-white mb-3">เพิ่มรีวิวของคุณ</p>
                <form method="POST">
                    <div class="flex gap-2 mb-3" id="starPicker">
                        <?php for ($i=1;$i<=5;$i++): ?>
                        <button type="button" onclick="setRating(<?=$i?>)"
                                class="text-2xl transition" data-star="<?=$i?>">⭐</button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="5">
                    <textarea name="comment" rows="2" placeholder="บอกเล่าประสบการณ์การชาร์จ..."
                              class="input-field w-full px-3 py-2 rounded-xl text-sm resize-none mb-3"></textarea>
                    <button type="submit" name="submit_review" class="btn-blue w-full py-2 rounded-xl text-sm">
                        ส่งรีวิว
                    </button>
                </form>
                <script>
                let selectedRating = 5;
                function setRating(n) {
                    selectedRating = n;
                    document.getElementById('ratingInput').value = n;
                    document.querySelectorAll('[data-star]').forEach(b => {
                        b.style.opacity = b.dataset.star <= n ? '1' : '0.3';
                    });
                }
                </script>
            </div>
            <?php endif; ?>

            <?php if (empty($reviews)): ?>
            <div class="glass rounded-xl p-6 text-center text-gray-500 text-sm">
                ยังไม่มีรีวิว — ชาร์จแล้วมาเขียนรีวิวก่อนนะ!
            </div>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($reviews as $rv): ?>
                <div class="glass rounded-xl p-3">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-semibold text-white"><?= htmlspecialchars($rv['full_name']) ?></p>
                        <div class="flex gap-0.5">
                            <?php for ($i=1;$i<=5;$i++) echo $i<=$rv['rating']?'⭐':'☆'; ?>
                        </div>
                    </div>
                    <?php if ($rv['comment']): ?>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($rv['comment']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-600 mt-1"><?= ago($rv['created_at']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php capp_bottom_nav('find'); ?>
<?php capp_foot(); ?>
