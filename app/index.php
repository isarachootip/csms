<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid  = capp_customer_id();
$cust = capp_customer();

// ── Active charging session
$active = DB::fetchOne(
    "SELECT t.*, s.name AS station_name, c.brand AS charger_brand, c.model AS charger_model,
            cn.connector_type, c.max_power_kw
     FROM transactions t
     JOIN stations s ON s.id = t.station_id
     JOIN chargers c ON c.id = t.charger_id
     JOIN connectors cn ON cn.id = t.connector_id
     WHERE t.customer_id=? AND t.status='Charging'
     ORDER BY t.start_time DESC LIMIT 1",
    [$cid]
);

// ── Monthly stats (current month)
$monthStats = DB::fetchOne(
    "SELECT COUNT(*) AS sessions, COALESCE(SUM(energy_kwh),0) AS kwh, COALESCE(SUM(actual_amount),0) AS spend
     FROM transactions WHERE customer_id=? AND status IN ('Completed','Stopped')
     AND MONTH(start_time)=MONTH(CURDATE()) AND YEAR(start_time)=YEAR(CURDATE())",
    [$cid]
);

// ── Recent sessions (last 3)
$recent = DB::fetchAll(
    "SELECT t.*, s.name AS station_name
     FROM transactions t
     JOIN stations s ON s.id = t.station_id
     WHERE t.customer_id=? AND t.status IN ('Completed','Stopped','Faulted')
     ORDER BY t.stop_time DESC LIMIT 3",
    [$cid]
);

// ── Nearby stations (latest 4, simplified — no GPS required for MVP)
$stations = DB::fetchAll(
    "SELECT s.*,
            COUNT(DISTINCT cn.id) AS total_connectors,
            SUM(cn.status='Ready to use') AS available,
            sfs.price_per_kwh
     FROM stations s
     LEFT JOIN chargers c ON c.station_id=s.id
     LEFT JOIN connectors cn ON cn.charger_id=c.id
     LEFT JOIN service_fee_settings sfs ON sfs.station_id=s.id AND sfs.is_active=1
     WHERE s.status='active'
     GROUP BY s.id ORDER BY s.id LIMIT 4"
);

$tier = tier_info((float)($cust['total_spend'] ?? 0));
$wallet = number_format((float)($cust['wallet_balance'] ?? 0), 2);
$welcome = isset($_GET['welcome']);

capp_head('หน้าแรก');
?>

<div class="page-content">
    <!-- Top Bar -->
    <div class="top-bar px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-900 font-bold text-sm"
                 style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <?= mb_substr($cust['full_name'] ?? 'U', 0, 1) ?>
            </div>
            <div>
                <p class="text-xs text-gray-400">สวัสดี 👋</p>
                <p class="text-sm font-bold text-white leading-none"><?= htmlspecialchars(explode(' ', $cust['full_name'] ?? 'ลูกค้า')[0]) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge-pill <?= "bg-gradient-to-r {$tier[2]}" ?> text-white text-xs">
                <?= $tier[1] ?> <?= $tier[0] ?>
            </span>
            <a href="notifications.php" class="relative w-9 h-9 flex items-center justify-center rounded-full hover:bg-blue-800/50">
                <span class="material-icons-round text-gray-300">notifications</span>
                <?php if (($cust['unread_notifs'] ?? 0) > 0): ?>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="px-4 space-y-4 pt-3">

        <?php if ($welcome): ?>
        <div class="glass rounded-2xl p-4 flex items-center gap-3 border border-green-500/30 slide-up">
            <span class="text-2xl">🎉</span>
            <div>
                <p class="font-bold text-green-300">ยินดีต้อนรับสู่ CSMS!</p>
                <p class="text-xs text-gray-400">สมัครสมาชิกสำเร็จแล้ว เริ่มชาร์จรถของคุณได้เลย</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ Active Session Banner ══ -->
        <?php if ($active): ?>
        <?php
            $elapsed = (int)((time() - strtotime($active['start_time'])) / 60);
            $elapsedSec = time() - strtotime($active['start_time']);
        ?>
        <a href="session.php?id=<?= $active['id'] ?>" class="block">
            <div class="rounded-2xl p-4 card-glow charging-pulse slide-up"
                 style="background:linear-gradient(135deg,#1e3a6e,#0f2a55);border:1px solid rgba(59,130,246,.5)">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 animate-ping"></span>
                        <span class="text-blue-300 font-bold text-sm">กำลังชาร์จอยู่...</span>
                    </div>
                    <span class="text-yellow-400 font-bold text-sm">⚡ LIVE</span>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-xs text-gray-400">เวลาที่ใช้</p>
                        <p class="text-lg font-bold text-white" id="liveTimer">
                            <?= sprintf('%02d:%02d', intdiv($elapsedSec,3600), intdiv($elapsedSec%3600,60)) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">พลังงาน</p>
                        <p class="text-lg font-bold text-yellow-400"><?= fmt_kwh((float)$active['energy_kwh']) ?> kWh</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">ค่าบริการ</p>
                        <p class="text-lg font-bold text-green-400">฿<?= fmt_thb((float)$active['actual_amount']) ?></p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-xs text-gray-400">📍 <?= htmlspecialchars($active['station_name']) ?></p>
                    <span class="text-xs text-blue-300">แตะเพื่อดูรายละเอียด →</span>
                </div>
            </div>
        </a>
        <?php endif; ?>

        <!-- ══ Wallet + Quick Actions ══ -->
        <div class="grid grid-cols-2 gap-3">
            <a href="wallet.php">
                <div class="glass rounded-2xl p-4 card-glow hover:border-yellow-500/40 transition">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-icons-round text-yellow-400 text-lg">account_balance_wallet</span>
                        <span class="text-xs text-gray-400">Wallet</span>
                    </div>
                    <p class="text-xl font-bold text-white">฿<?= $wallet ?></p>
                    <p class="text-xs text-yellow-400 mt-1">แตะเพื่อเติมเงิน →</p>
                </div>
            </a>
            <a href="charge.php">
                <div class="rounded-2xl p-4 card-glow hover:opacity-90 transition"
                     style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-icons-round text-gray-900 text-lg">bolt</span>
                        <span class="text-xs text-gray-800 font-semibold">ชาร์จเลย</span>
                    </div>
                    <p class="text-xl font-bold text-gray-900">เริ่มชาร์จ</p>
                    <p class="text-xs text-gray-800 mt-1">เลือกสถานีใกล้คุณ →</p>
                </div>
            </a>
        </div>

        <!-- ══ Monthly Stats ══ -->
        <div class="glass rounded-2xl p-4 card-glow">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-white flex items-center gap-1">
                    <span class="material-icons-round text-blue-400 text-base">bar_chart</span>
                    สถิติเดือนนี้
                </p>
                <span class="text-xs text-gray-500"><?= date('M Y') ?></span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-blue-900/40 rounded-xl py-3">
                    <p class="text-xl font-bold text-blue-300"><?= $monthStats['sessions'] ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">ครั้ง</p>
                </div>
                <div class="bg-yellow-900/30 rounded-xl py-3">
                    <p class="text-xl font-bold text-yellow-300"><?= fmt_kwh((float)$monthStats['kwh']) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">kWh</p>
                </div>
                <div class="bg-green-900/30 rounded-xl py-3">
                    <p class="text-xl font-bold text-green-300">฿<?= number_format((float)$monthStats['spend'], 0) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">บาท</p>
                </div>
            </div>
            <?php
            $co2 = (float)$monthStats['kwh'] * 0.5;
            $fuel = (float)$monthStats['kwh'] * 3.5;
            ?>
            <div class="flex gap-3 mt-3">
                <div class="flex-1 flex items-center gap-2 bg-green-900/20 rounded-xl px-3 py-2">
                    <span class="text-green-400 text-lg">🌱</span>
                    <div>
                        <p class="text-xs text-gray-400">CO₂ ลด</p>
                        <p class="text-sm font-bold text-green-300"><?= number_format($co2, 1) ?> kg</p>
                    </div>
                </div>
                <div class="flex-1 flex items-center gap-2 bg-blue-900/20 rounded-xl px-3 py-2">
                    <span class="text-blue-400 text-lg">⛽</span>
                    <div>
                        <p class="text-xs text-gray-400">ประหยัดน้ำมัน</p>
                        <p class="text-sm font-bold text-blue-300">฿<?= number_format($fuel, 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ Nearby Stations ══ -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-white flex items-center gap-1">
                    <span class="material-icons-round text-yellow-400 text-base">ev_station</span>
                    สถานีชาร์จ
                </p>
                <a href="stations.php" class="text-xs text-blue-400">ดูทั้งหมด →</a>
            </div>
            <div class="space-y-2">
                <?php foreach ($stations as $st): ?>
                <?php
                    $avail  = (int)$st['available'];
                    $total  = (int)$st['total_connectors'];
                    $pct    = $total > 0 ? round($avail / $total * 100) : 0;
                    $stColor = $avail > 0 ? 'text-green-400' : 'text-red-400';
                    $stBg    = $avail > 0 ? 'border-green-500/20' : 'border-red-500/20';
                ?>
                <a href="station_detail.php?id=<?= $st['id'] ?>">
                    <div class="glass rounded-xl px-4 py-3 flex items-center gap-3 <?= $stBg ?> hover:border-blue-500/40 transition">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background:rgba(59,130,246,.15)">
                            <span class="material-icons-round text-blue-400">ev_station</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($st['name']) ?></p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($st['location'] ?? $st['address'] ?? '-') ?></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold <?= $stColor ?>"><?= $avail ?>/<?= $total ?></p>
                            <p class="text-xs text-gray-500">ว่าง</p>
                            <?php if ($st['price_per_kwh'] > 0): ?>
                            <p class="text-xs text-yellow-400">฿<?= number_format((float)$st['price_per_kwh'], 2) ?>/kWh</p>
                            <?php else: ?>
                            <p class="text-xs text-green-400 font-bold">ฟรี!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($stations)): ?>
                <div class="glass rounded-xl px-4 py-8 text-center text-gray-500">
                    <span class="material-icons-round text-4xl block mb-2">ev_station</span>
                    ยังไม่มีสถานีชาร์จ
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ Recent Sessions ══ -->
        <?php if (!empty($recent)): ?>
        <div>
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-white flex items-center gap-1">
                    <span class="material-icons-round text-purple-400 text-base">history</span>
                    การชาร์จล่าสุด
                </p>
                <a href="history.php" class="text-xs text-blue-400">ดูทั้งหมด →</a>
            </div>
            <div class="space-y-2">
                <?php foreach ($recent as $tx): ?>
                <a href="history.php?tx=<?= $tx['id'] ?>">
                    <div class="glass rounded-xl px-4 py-3 flex items-center gap-3 hover:border-blue-500/30 transition">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-purple-900/40">
                            <span class="material-icons-round text-purple-400 text-lg">bolt</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($tx['station_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= ago($tx['stop_time'] ?? $tx['start_time']) ?> · <?= fmt_kwh((float)$tx['energy_kwh']) ?> kWh</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold text-white">฿<?= fmt_thb((float)$tx['actual_amount']) ?></p>
                            <p class="text-xs <?= $tx['status'] === 'Completed' ? 'text-green-400' : 'text-gray-400' ?>"><?= $tx['status'] === 'Completed' ? 'สำเร็จ' : $tx['status'] ?></p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ Vehicle Card ══ -->
        <?php
        $vehicle = DB::fetchOne(
            "SELECT cv.*, ct.name AS car_name, ct.brand, ct.connector_type, ct.battery_kwh
             FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id
             WHERE cv.customer_id=? AND cv.is_default=1 LIMIT 1", [$cid]);
        if (!$vehicle) {
            $vehicle = DB::fetchOne(
                "SELECT cv.*, ct.name AS car_name, ct.brand, ct.connector_type, ct.battery_kwh
                 FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id
                 WHERE cv.customer_id=? LIMIT 1", [$cid]);
        }
        ?>
        <?php if ($vehicle): ?>
        <a href="profile.php#vehicles">
            <div class="glass rounded-2xl p-4 flex items-center gap-4 card-glow hover:border-blue-500/40 transition">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl"
                     style="background:rgba(59,130,246,.1)">🚗</div>
                <div class="flex-1">
                    <p class="font-bold text-white"><?= htmlspecialchars($vehicle['car_name'] ?? 'รถของฉัน') ?></p>
                    <p class="text-sm text-gray-400"><?= htmlspecialchars($vehicle['license_plate']) ?></p>
                    <?php if ($vehicle['connector_type']): ?>
                    <span class="connector-badge border-blue-700/50 text-blue-300 mt-1">
                        <?= connector_icon($vehicle['connector_type']) ?> <?= $vehicle['connector_type'] ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($vehicle['battery_kwh'] > 0): ?>
                <div class="text-center">
                    <p class="text-lg font-bold text-yellow-400"><?= $vehicle['battery_kwh'] ?></p>
                    <p class="text-xs text-gray-500">kWh</p>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php else: ?>
        <a href="profile.php#vehicles">
            <div class="glass rounded-2xl p-4 border border-dashed border-blue-700/40 text-center hover:border-yellow-500/40 transition">
                <span class="material-icons-round text-gray-500 text-2xl mb-1 block">add_circle_outline</span>
                <p class="text-sm text-gray-400">เพิ่มข้อมูลรถของคุณ</p>
            </div>
        </a>
        <?php endif; ?>

    </div>
</div>

<?php capp_bottom_nav('home'); ?>

<script>
// Live timer update for active session
(function(){
    const el = document.getElementById('liveTimer');
    if (!el) return;
    const startTs = <?= $active ? strtotime($active['start_time']) : 0 ?>;
    if (!startTs) return;
    function update() {
        const s = Math.floor(Date.now()/1000) - startTs;
        if (s < 0) return;
        const h = Math.floor(s/3600);
        const m = Math.floor((s%3600)/60);
        const sc = s%60;
        el.textContent = (h>0 ? String(h).padStart(2,'0')+':' : '') +
                         String(m).padStart(2,'0')+':'+String(sc).padStart(2,'0');
    }
    update();
    setInterval(update, 1000);
})();
</script>

<?php capp_foot(); ?>
