<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid  = capp_customer_id();
$txId = (int)($_GET['id'] ?? 0);
if (!$txId) { header('Location: index.php'); exit; }

$tx = DB::fetchOne(
    "SELECT t.*, s.name AS station_name, s.address AS station_addr,
            c.brand AS charger_brand, c.model AS charger_model, c.max_power_kw,
            cn.connector_type
     FROM transactions t
     JOIN stations s  ON s.id  = t.station_id
     JOIN chargers c  ON c.id  = t.charger_id
     JOIN connectors cn ON cn.id = t.connector_id
     WHERE t.id=? AND t.customer_id=?",
    [$txId, $cid]
);
if (!$tx) { header('Location: index.php'); exit; }

// ── POST: Stop Charging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stop') {
    if ($tx['status'] === 'Charging') {
        $now     = date('Y-m-d H:i:s');
        $elapsed = max(1, (int)((time() - strtotime($tx['start_time'])) / 60));
        $kwh     = round($elapsed * (float)$tx['max_power_kw'] / 60, 4);
        $actual  = round($kwh * (float)$tx['price_per_kwh'], 2);

        DB::execute(
            "UPDATE transactions SET status='Completed', stop_time=?, duration_minutes=?,
                energy_kwh=?, actual_amount=? WHERE id=?",
            [$now, $elapsed, $kwh, $actual, $txId]
        );
        DB::execute("UPDATE connectors SET status='Ready to use' WHERE id=?", [$tx['connector_id']]);

        // Deduct wallet
        $wRow = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$cid]);
        if ($wRow && $actual > 0) {
            $newBal = max(0, (float)$wRow['balance'] - $actual);
            DB::execute("UPDATE wallet_accounts SET balance=? WHERE id=?", [$newBal, $wRow['id']]);
            DB::insert("INSERT INTO wallet_transactions (wallet_id,type,amount,balance_after,description)
                        VALUES (?,'charge',?,?,?)",
                [$wRow['id'], $actual, $newBal, "ชาร์จ Tx#{$txId} " . $tx['station_name']]);
        }

        // Update customer stats
        DB::execute("UPDATE customers SET total_sessions=total_sessions+1, total_kwh=total_kwh+?, total_spend=total_spend+? WHERE id=?",
            [$kwh, $actual, $cid]);

        // Notification
        DB::insert("INSERT INTO customer_notifications (customer_id,type,title,body,icon) VALUES (?,?,?,?,?)",
            [$cid,'session','ชาร์จเสร็จแล้ว! ✅',
             "ชาร์จ {$kwh} kWh ค่าบริการ ฿{$actual} ที่ " . $tx['station_name'], 'check_circle']);

        header("Location: session.php?id=$txId&done=1"); exit;
    }
}

// Reload after stop
$tx = DB::fetchOne(
    "SELECT t.*, s.name AS station_name, c.brand AS charger_brand, c.model AS charger_model, c.max_power_kw, cn.connector_type
     FROM transactions t JOIN stations s ON s.id=t.station_id JOIN chargers c ON c.id=t.charger_id JOIN connectors cn ON cn.id=t.connector_id
     WHERE t.id=? AND t.customer_id=?",
    [$txId, $cid]
);

$isDone = $tx['status'] !== 'Charging' || isset($_GET['done']);
$startTs = strtotime($tx['start_time']);
$elapsed = (int)((($isDone && $tx['stop_time'] ? strtotime($tx['stop_time']) : time()) - $startTs) / 60);
$kwh     = (float)$tx['energy_kwh'];
$actual  = (float)$tx['actual_amount'];
$estKwh  = (float)$tx['estimate_amount'] > 0 ? (float)$tx['estimate_amount'] / max(0.01, (float)$tx['price_per_kwh']) : 0;
$pct     = $estKwh > 0 ? min(100, round($kwh / $estKwh * 100)) : 0;

capp_head($isDone ? 'ชาร์จเสร็จแล้ว' : 'กำลังชาร์จ...');
?>

<div class="page-content pb-24">
    <?php if ($isDone): ?>
    <!-- ══ DONE SCREEN ══ -->
    <div class="px-4 pt-6 space-y-4 text-center">
        <div class="py-8">
            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background:linear-gradient(135deg,rgba(34,197,94,.3),rgba(16,185,129,.2));border:2px solid rgba(34,197,94,.5)">
                <span class="material-icons-round text-5xl text-green-400">check_circle</span>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">ชาร์จเสร็จแล้ว! 🎉</h1>
            <p class="text-sm text-gray-400"><?= htmlspecialchars($tx['station_name']) ?></p>
        </div>

        <!-- Summary Cards -->
        <div class="glass rounded-2xl p-5 card-glow text-left">
            <p class="text-sm font-bold text-white mb-4">สรุปการชาร์จ</p>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-900/40 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 mb-1">⏱ เวลาที่ใช้</p>
                    <p class="text-xl font-bold text-blue-300"><?= fmt_dur($elapsed) ?></p>
                </div>
                <div class="bg-yellow-900/30 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 mb-1">⚡ พลังงาน</p>
                    <p class="text-xl font-bold text-yellow-300"><?= fmt_kwh($kwh) ?> kWh</p>
                </div>
                <div class="bg-green-900/30 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 mb-1">💰 ค่าบริการ</p>
                    <p class="text-xl font-bold text-green-300">฿<?= fmt_thb($actual) ?></p>
                </div>
                <div class="bg-purple-900/30 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 mb-1">🌱 CO₂ ลด</p>
                    <p class="text-xl font-bold text-purple-300"><?= number_format($kwh*0.5,1) ?> kg</p>
                </div>
            </div>
            <div class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between text-gray-400">
                    <span>สถานี</span><span class="text-white"><?= htmlspecialchars($tx['station_name']) ?></span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>หัวชาร์จ</span><span class="text-white"><?= $tx['connector_type'] ?></span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>ราคา/kWh</span><span class="text-white">฿<?= number_format((float)$tx['price_per_kwh'],2) ?></span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>เริ่มชาร์จ</span><span class="text-white"><?= date('d/m/Y H:i', $startTs) ?></span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>สิ้นสุด</span><span class="text-white"><?= $tx['stop_time'] ? date('d/m/Y H:i', strtotime($tx['stop_time'])) : '-' ?></span>
                </div>
            </div>
        </div>

        <!-- Rate Station -->
        <?php $alreadyReviewed = DB::fetchOne("SELECT id FROM station_reviews WHERE customer_id=? AND transaction_id=?",[$cid,$txId]); ?>
        <?php if (!$alreadyReviewed): ?>
        <div class="glass rounded-2xl p-4 text-left">
            <p class="text-sm font-bold text-white mb-3">⭐ ให้คะแนนสถานีนี้</p>
            <form method="POST" action="station_detail.php?id=<?= $tx['station_id'] ?>">
                <input type="hidden" name="submit_review" value="1">
                <input type="hidden" name="rating" id="ratingVal" value="5">
                <div class="flex justify-center gap-3 mb-3" id="stars">
                    <?php for ($i=1;$i<=5;$i++): ?>
                    <button type="button" data-s="<?=$i?>" onclick="setStar(<?=$i?>)"
                            class="text-3xl transition">⭐</button>
                    <?php endfor; ?>
                </div>
                <textarea name="comment" rows="2" placeholder="บอกเล่าประสบการณ์..."
                          class="input-field w-full px-3 py-2 rounded-xl text-sm resize-none mb-3"></textarea>
                <button type="submit" class="btn-blue w-full py-2.5 rounded-xl text-sm">ส่งรีวิว</button>
            </form>
        </div>
        <script>
        function setStar(n) {
            document.getElementById('ratingVal').value = n;
            document.querySelectorAll('#stars button').forEach(b => b.style.opacity = b.dataset.s <= n ? '1' : '0.3');
        }
        </script>
        <?php endif; ?>

        <div class="flex gap-3 pb-4">
            <a href="history.php" class="flex-1 py-3 rounded-xl border border-gray-700 text-gray-300 text-sm text-center">
                ดูประวัติ
            </a>
            <a href="index.php" class="flex-1 btn-primary py-3 rounded-xl text-sm text-center font-bold">
                กลับหน้าแรก
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ══ LIVE CHARGING SCREEN ══ -->
    <div class="min-h-screen flex flex-col" style="background:linear-gradient(160deg,#0a1628,#0d2040,#0f2855)">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-4">
            <a href="index.php" class="text-gray-400">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <span class="text-sm font-semibold text-blue-300">⚡ กำลังชาร์จ</span>
            <div class="w-6"></div>
        </div>

        <!-- Station name -->
        <div class="text-center px-4 mb-4">
            <p class="text-xs text-gray-500">📍 <?= htmlspecialchars($tx['station_name']) ?></p>
            <p class="text-xs text-blue-300 mt-0.5"><?= $tx['connector_type'] ?> · <?= (int)$tx['max_power_kw'] ?> kW</p>
        </div>

        <!-- Battery Animation -->
        <div class="flex-1 flex flex-col items-center justify-center px-6">
            <div class="relative w-36 h-36 mb-6">
                <!-- Outer ring -->
                <svg class="absolute inset-0 -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(59,130,246,.15)" stroke-width="8"/>
                    <circle cx="50" cy="50" r="45" fill="none" stroke="url(#chargeGrad)" stroke-width="8"
                            stroke-linecap="round"
                            stroke-dasharray="<?= round($pct * 2.827) ?> 282.7"
                            id="progressCircle"/>
                    <defs>
                        <linearGradient id="chargeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#3b82f6"/>
                            <stop offset="100%" stop-color="#f59e0b"/>
                        </linearGradient>
                    </defs>
                </svg>
                <!-- Inner content -->
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="material-icons-round text-yellow-400 text-4xl charging-pulse">bolt</span>
                    <p class="text-sm font-bold text-white" id="liveKwh"><?= fmt_kwh($kwh) ?></p>
                    <p class="text-xs text-gray-400">kWh</p>
                </div>
            </div>

            <!-- Timer -->
            <div class="text-center mb-6">
                <p class="text-5xl font-bold text-white tabular-nums" id="liveTimer">00:00:00</p>
                <p class="text-xs text-gray-500 mt-1">เวลาที่ชาร์จมา</p>
            </div>

            <!-- Stats -->
            <div class="w-full grid grid-cols-2 gap-3 mb-6">
                <div class="glass rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">💰 ค่าบริการ</p>
                    <p class="text-2xl font-bold text-green-300" id="liveCost">฿<?= fmt_thb($actual) ?></p>
                </div>
                <div class="glass rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">⚡ ราคา/kWh</p>
                    <p class="text-2xl font-bold text-yellow-300">฿<?= number_format((float)$tx['price_per_kwh'],2) ?></p>
                </div>
            </div>

            <!-- Progress bar -->
            <?php if ($estKwh > 0): ?>
            <div class="w-full mb-6">
                <div class="flex justify-between text-xs text-gray-400 mb-1.5">
                    <span>ความคืบหน้า</span>
                    <span id="pctLabel"><?= $pct ?>%</span>
                </div>
                <div class="h-3 bg-blue-950 rounded-full">
                    <div id="progressBar" class="h-full rounded-full transition-all"
                         style="width:<?= $pct ?>%;background:linear-gradient(90deg,#3b82f6,#f59e0b)"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>เริ่ม</span>
                    <span>เป้าหมาย: <?= fmt_kwh($estKwh) ?> kWh</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stop Button -->
            <form method="POST" id="stopForm">
                <input type="hidden" name="action" value="stop">
                <button type="button" onclick="confirmStop()"
                        class="btn-red w-64 py-4 rounded-2xl text-lg font-bold flex items-center justify-center gap-2 mx-auto">
                    <span class="material-icons-round">stop_circle</span>
                    หยุดชาร์จ
                </button>
            </form>
            <p class="text-xs text-gray-600 mt-2 text-center">ยืนยัน 2 ครั้งก่อนหยุด</p>
        </div>
    </div>

    <!-- Stop Confirm Modal -->
    <div id="stopModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6"
         style="background:rgba(0,0,0,.7);backdrop-filter:blur(8px)">
        <div class="glass rounded-2xl p-6 w-full max-w-sm slide-up">
            <div class="text-center mb-4">
                <span class="text-5xl">⏹️</span>
                <h3 class="text-xl font-bold text-white mt-3">หยุดชาร์จ?</h3>
                <p class="text-sm text-gray-400 mt-1">ระบบจะคำนวณค่าบริการตามจริง</p>
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('stopModal').classList.add('hidden')"
                        class="flex-1 py-3 rounded-xl border border-gray-700 text-gray-300">ยกเลิก</button>
                <button onclick="document.getElementById('stopForm').submit()"
                        class="flex-1 btn-red py-3 rounded-xl font-bold">หยุดเลย</button>
            </div>
        </div>
    </div>

    <script>
    const startTs   = <?= $startTs ?>;
    const priceKwh  = <?= (float)$tx['price_per_kwh'] ?>;
    const maxKw     = <?= (float)$tx['max_power_kw'] ?>;
    const estKwh    = <?= $estKwh ?>;

    function pad(n){return String(n).padStart(2,'0')}

    function update() {
        const now    = Math.floor(Date.now()/1000);
        const s      = now - startTs;
        const h      = Math.floor(s/3600);
        const m      = Math.floor((s%3600)/60);
        const sc     = s%60;
        const elTimer = document.getElementById('liveTimer');
        if (elTimer) elTimer.textContent = pad(h)+':'+pad(m)+':'+pad(sc);

        // Simulated kWh (based on max_kw, assumes full power)
        const simKwh  = parseFloat((s * maxKw / 3600).toFixed(3));
        const simCost = parseFloat((simKwh * priceKwh).toFixed(2));
        const kwhEl   = document.getElementById('liveKwh');
        const costEl  = document.getElementById('liveCost');
        const pBar    = document.getElementById('progressBar');
        const pLabel  = document.getElementById('pctLabel');
        const circ    = document.getElementById('progressCircle');
        if (kwhEl) kwhEl.textContent = simKwh.toFixed(2);
        if (costEl) costEl.textContent = '฿'+simCost.toFixed(2);
        if (estKwh > 0) {
            const pct = Math.min(100, Math.round(simKwh/estKwh*100));
            if (pBar)   pBar.style.width = pct+'%';
            if (pLabel) pLabel.textContent = pct+'%';
            if (circ)   circ.setAttribute('stroke-dasharray', (pct*2.827)+' 282.7');
        }
    }
    update();
    const iv = setInterval(update, 1000);

    function confirmStop() {
        document.getElementById('stopModal').classList.remove('hidden');
    }
    </script>
    <?php endif; ?>
</div>

<?php capp_foot(); ?>
