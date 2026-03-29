<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid = capp_customer_id();
$cust = capp_customer();

$preConnId = (int)($_GET['connector_id'] ?? 0);
$preStnId  = (int)($_GET['station_id']  ?? 0);

// ── POST: Start Charging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    $connId  = (int)($_POST['connector_id'] ?? 0);
    $amount  = (float)($_POST['target_amount'] ?? 0);
    $kwh     = (float)($_POST['target_kwh']    ?? 0);
    $payment = $_POST['payment_method'] ?? 'wallet';
    $vehId   = (int)($_POST['vehicle_id'] ?? 0);

    $conn = DB::fetchOne(
        "SELECT cn.*, c.station_id, c.id AS charger_id, c.max_power_kw,
                sfs.price_per_kwh, sfs.fee_type
         FROM connectors cn
         JOIN chargers c ON c.id=cn.charger_id
         LEFT JOIN service_fee_settings sfs ON sfs.station_id=c.station_id AND sfs.is_active=1
         WHERE cn.id=? AND cn.status='Ready to use'",
        [$connId]
    );

    if (!$conn) {
        capp_flash('charge','หัวชาร์จนี้ไม่ว่างหรือไม่พร้อมใช้งาน','error');
        header('Location: charge.php'); exit;
    }

    $priceKwh = (float)($conn['price_per_kwh'] ?? 0);
    $estKwh   = $amount > 0 && $priceKwh > 0 ? round($amount / $priceKwh, 2) : $kwh;
    $estAmt   = $kwh > 0 && $priceKwh > 0 ? round($kwh * $priceKwh, 2) : $amount;

    // Check wallet balance if using wallet
    $walletRow = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$cid]);
    if ($payment === 'wallet' && $priceKwh > 0 && $estAmt > 0) {
        if (($walletRow['balance'] ?? 0) < $estAmt) {
            capp_flash('charge', 'ยอด Wallet ไม่เพียงพอ กรุณาเติมเงินก่อน', 'error');
            header('Location: charge.php?connector_id='.$connId.'&station_id='.$conn['station_id']); exit;
        }
    }

    // Get vehicle/customer info
    $veh = $vehId ? DB::fetchOne("SELECT cv.*, ct.name AS car_name FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id WHERE cv.id=? AND cv.customer_id=?", [$vehId,$cid]) : null;
    $carTypeId = $veh ? DB::fetchOne("SELECT car_type_id FROM customer_vehicles WHERE id=?",[$vehId])['car_type_id'] ?? null : null;

    $txId = DB::insert(
        "INSERT INTO transactions (connector_id,charger_id,station_id,user_id,customer_id,car_type_id,
            estimate_amount,actual_amount,energy_kwh,start_time,status,fee_type,price_per_kwh,remark)
         VALUES (?,?,?,?,?,?,?,0,0,NOW(),'Charging',?,?,?)",
        [$connId, $conn['charger_id'], $conn['station_id'], 1, $cid, $carTypeId,
         $estAmt, $conn['fee_type']??'kWh-Based', $priceKwh,
         $veh ? $veh['car_name'].' '.$veh['license_plate'] : '']
    );
    DB::execute("UPDATE connectors SET status='Charging in progress' WHERE id=?", [$connId]);

    // Notify
    DB::insert("INSERT INTO customer_notifications (customer_id,type,title,body,icon) VALUES (?,?,?,?,?)",
        [$cid,'session','เริ่มชาร์จแล้ว ⚡','ชาร์จที่ connector #'.$connId.' สำเร็จแล้ว','bolt']);

    header("Location: session.php?id=$txId"); exit;
}

// ── Load connector info if pre-selected
$selectedConn = null;
$selectedStation = null;
if ($preConnId) {
    $selectedConn = DB::fetchOne(
        "SELECT cn.*, c.station_id, c.id AS charger_id, c.max_power_kw, c.brand, c.model,
                s.name AS station_name, sfs.price_per_kwh, sfs.fee_type
         FROM connectors cn
         JOIN chargers c ON c.id=cn.charger_id
         JOIN stations s ON s.id=c.station_id
         LEFT JOIN service_fee_settings sfs ON sfs.station_id=c.station_id AND sfs.is_active=1
         WHERE cn.id=?",
        [$preConnId]
    );
    if ($selectedConn) $selectedStation = $selectedConn;
}

// All available connectors for manual selection
$allStations = DB::fetchAll(
    "SELECT s.id, s.name, s.location,
            GROUP_CONCAT(DISTINCT cn.id ORDER BY cn.id SEPARATOR ',') AS conn_ids
     FROM stations s
     JOIN chargers c ON c.station_id=s.id
     JOIN connectors cn ON cn.charger_id=c.id
     WHERE s.status='active' AND cn.status='Ready to use'
     GROUP BY s.id ORDER BY s.name"
);

$vehicles = DB::fetchAll(
    "SELECT cv.*, ct.name AS car_name, ct.brand, ct.connector_type, ct.battery_kwh
     FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id
     WHERE cv.customer_id=? ORDER BY cv.is_default DESC, cv.id",
    [$cid]
);

$flash = capp_flash_html('charge');
capp_head('เริ่มชาร์จ');
?>

<div class="page-content">
    <?php capp_top_bar('เริ่มชาร์จ', true); ?>

    <div class="px-4 pt-3 space-y-4">
        <?= $flash ?>

        <!-- ── Step: Select Connector (if not pre-selected) ── -->
        <?php if (!$selectedConn): ?>
        <div class="glass rounded-2xl p-4 card-glow">
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <span class="material-icons-round text-yellow-400">cable</span>
                เลือกหัวชาร์จ
            </p>
            <?php if (empty($allStations)): ?>
            <p class="text-center text-gray-400 py-8">ไม่มีหัวชาร์จว่างในขณะนี้</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($allStations as $st): ?>
                <?php
                    $connIds = array_filter(explode(',', $st['conn_ids']));
                    $conns = empty($connIds) ? [] : DB::fetchAll(
                        "SELECT cn.*, c.max_power_kw, c.brand FROM connectors cn JOIN chargers c ON c.id=cn.charger_id WHERE cn.id IN (" . implode(',',array_fill(0,count($connIds),'?')) . ")",
                        $connIds
                    );
                ?>
                <div class="border border-blue-800/40 rounded-xl p-3">
                    <p class="text-sm font-semibold text-blue-200 mb-2">📍 <?= htmlspecialchars($st['name']) ?></p>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($conns as $cn): ?>
                        <a href="charge.php?connector_id=<?= $cn['id'] ?>&station_id=<?= $st['id'] ?>"
                           class="bg-green-900/30 border border-green-600/30 rounded-xl p-2.5 hover:border-green-400/60 transition">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-base"><?= connector_icon($cn['connector_type']) ?></span>
                                <span class="text-xs font-bold text-green-300"><?= (int)$cn['max_power_kw'] ?> kW</span>
                            </div>
                            <p class="text-xs text-white font-semibold"><?= $cn['connector_type'] ?></p>
                            <p class="text-xs text-green-300 mt-0.5">✅ ว่าง</p>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <!-- ── Connector Info ── -->
        <div class="glass rounded-2xl p-4 border border-green-500/30">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                     style="background:rgba(34,197,94,.1)">
                    <?= connector_icon($selectedConn['connector_type']) ?>
                </div>
                <div>
                    <p class="font-bold text-white"><?= htmlspecialchars($selectedConn['station_name']) ?></p>
                    <p class="text-sm text-green-300"><?= $selectedConn['connector_type'] ?> · <?= (int)$selectedConn['max_power_kw'] ?> kW</p>
                    <?php if ($selectedConn['price_per_kwh'] > 0): ?>
                    <p class="text-xs text-yellow-400">฿<?= number_format((float)$selectedConn['price_per_kwh'],2) ?>/kWh</p>
                    <?php else: ?>
                    <p class="text-xs text-green-400 font-bold">ฟรี!</p>
                    <?php endif; ?>
                </div>
                <div class="ml-auto">
                    <span class="badge-pill bg-green-500/20 text-green-300 border border-green-500/30">✅ ว่าง</span>
                </div>
            </div>
        </div>

        <!-- ── Charge Form ── -->
        <form method="POST" id="chargeForm">
            <input type="hidden" name="action" value="start">
            <input type="hidden" name="connector_id" value="<?= $preConnId ?>">

            <!-- Target Amount Selector -->
            <div class="glass rounded-2xl p-4 card-glow">
                <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <span class="material-icons-round text-yellow-400">payments</span>
                    เลือกยอดที่ต้องการชาร์จ
                </p>
                <!-- Quick amount buttons -->
                <div class="grid grid-cols-4 gap-2 mb-3">
                    <?php foreach ([50,100,200,500] as $amt): ?>
                    <button type="button" onclick="setAmount(<?= $amt ?>)"
                            class="amount-btn py-2.5 rounded-xl text-sm font-bold border border-blue-700/50 text-blue-200 hover:border-yellow-500/60 hover:text-yellow-400 transition">
                        ฿<?= $amt ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="relative mb-3">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-yellow-400 font-bold">฿</span>
                    <input type="number" name="target_amount" id="targetAmt" placeholder="กำหนดเอง"
                           class="input-field w-full pl-8 pr-4 py-3 rounded-xl text-sm" min="1"
                           oninput="updatePreview()">
                </div>

                <div class="flex items-center gap-2 mb-3">
                    <div class="flex-1 h-px bg-blue-900"></div>
                    <span class="text-xs text-gray-500">หรือ</span>
                    <div class="flex-1 h-px bg-blue-900"></div>
                </div>

                <div class="relative">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-300 text-xs font-bold">kWh</span>
                    <input type="number" name="target_kwh" id="targetKwh" placeholder="กรอก kWh"
                           class="input-field w-full pl-4 pr-14 py-3 rounded-xl text-sm" min="1" max="200"
                           oninput="updatePreview()">
                </div>

                <!-- Preview -->
                <div id="preview" class="mt-3 p-3 rounded-xl bg-blue-950/60 hidden">
                    <div class="grid grid-cols-2 gap-2 text-center text-sm">
                        <div>
                            <p class="text-gray-400 text-xs">ประมาณ kWh</p>
                            <p class="font-bold text-yellow-300" id="previewKwh">-</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs">ประมาณค่าบริการ</p>
                            <p class="font-bold text-green-300" id="previewAmt">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Selection -->
            <?php if (!empty($vehicles)): ?>
            <div class="glass rounded-2xl p-4">
                <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <span class="material-icons-round text-blue-400">directions_car</span>
                    เลือกรถ
                </p>
                <div class="space-y-2">
                    <?php foreach ($vehicles as $v): ?>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-blue-800/40 cursor-pointer hover:border-blue-500/60 transition has-[:checked]:border-yellow-500/60 has-[:checked]:bg-yellow-900/10">
                        <input type="radio" name="vehicle_id" value="<?= $v['id'] ?>" <?= $v['is_default'] ? 'checked' : '' ?> class="accent-yellow-400">
                        <span class="text-xl">🚗</span>
                        <div>
                            <p class="text-sm font-semibold text-white"><?= htmlspecialchars($v['car_name'] ?? 'รถของฉัน') ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($v['license_plate']) ?> · <?= $v['connector_type'] ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Method -->
            <div class="glass rounded-2xl p-4">
                <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <span class="material-icons-round text-green-400">account_balance_wallet</span>
                    วิธีชำระเงิน
                </p>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-green-700/40 cursor-pointer hover:border-green-500/60 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-900/10">
                        <input type="radio" name="payment_method" value="wallet" checked class="accent-green-400">
                        <span class="material-icons-round text-green-400">account_balance_wallet</span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-white">Wallet</p>
                            <p class="text-xs text-green-300">ยอดคงเหลือ: ฿<?= number_format((float)($cust['wallet_balance']??0),2) ?></p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-blue-700/40 cursor-pointer hover:border-blue-500/60 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-900/10">
                        <input type="radio" name="payment_method" value="promptpay" class="accent-blue-400">
                        <span class="text-xl">🏦</span>
                        <div>
                            <p class="text-sm font-semibold text-white">PromptPay</p>
                            <p class="text-xs text-gray-400">สแกน QR จ่ายทันที</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <button type="button" onclick="confirmCharge()"
                    class="btn-primary w-full py-4 rounded-2xl text-lg font-bold flex items-center justify-center gap-2">
                <span class="material-icons-round">bolt</span>
                เริ่มชาร์จเลย!
            </button>

            <!-- Confirm Modal -->
            <div id="confirmModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6"
                 style="background:rgba(0,0,0,.7);backdrop-filter:blur(8px)">
                <div class="glass rounded-2xl p-6 w-full max-w-sm slide-up">
                    <div class="text-center mb-4">
                        <span class="text-5xl">⚡</span>
                        <h3 class="text-xl font-bold text-white mt-2">ยืนยันการชาร์จ</h3>
                        <p class="text-sm text-gray-400 mt-1"><?= htmlspecialchars($selectedConn['station_name']) ?></p>
                    </div>
                    <div id="confirmDetails" class="bg-blue-950/60 rounded-xl p-4 mb-4 space-y-2 text-sm"></div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal()"
                                class="flex-1 py-3 rounded-xl border border-gray-700 text-gray-300">ยกเลิก</button>
                        <button type="submit"
                                class="flex-1 btn-primary py-3 rounded-xl text-base">ยืนยัน!</button>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php capp_bottom_nav('charge'); ?>

<script>
const pricePerKwh = <?= (float)($selectedConn['price_per_kwh'] ?? 0) ?>;

function setAmount(a) {
    document.getElementById('targetAmt').value = a;
    document.getElementById('targetKwh').value = '';
    document.querySelectorAll('.amount-btn').forEach(b => {
        b.classList.toggle('border-yellow-500', b.textContent.trim()==='฿'+a);
        b.classList.toggle('text-yellow-400', b.textContent.trim()==='฿'+a);
    });
    updatePreview();
}

function updatePreview() {
    const amt = parseFloat(document.getElementById('targetAmt')?.value||0);
    const kwh = parseFloat(document.getElementById('targetKwh')?.value||0);
    const prev = document.getElementById('preview');
    const pKwh = document.getElementById('previewKwh');
    const pAmt = document.getElementById('previewAmt');
    if (!prev) return;
    if (amt > 0 || kwh > 0) {
        prev.classList.remove('hidden');
        if (amt > 0 && pricePerKwh > 0) {
            pKwh.textContent = (amt/pricePerKwh).toFixed(2)+' kWh';
            pAmt.textContent = '฿'+amt.toFixed(2);
        } else if (kwh > 0) {
            pKwh.textContent = kwh+' kWh';
            pAmt.textContent = pricePerKwh > 0 ? '฿'+(kwh*pricePerKwh).toFixed(2) : 'ฟรี';
        }
    } else { prev.classList.add('hidden'); }
}

function confirmCharge() {
    const amt = parseFloat(document.getElementById('targetAmt')?.value||0);
    const kwh = parseFloat(document.getElementById('targetKwh')?.value||0);
    if (!amt && !kwh) {
        alert('กรุณาเลือกยอดหรือ kWh ที่ต้องการชาร์จ');
        return;
    }
    const details = document.getElementById('confirmDetails');
    let html = '';
    if (amt > 0) html += `<div class="flex justify-between"><span class="text-gray-400">ยอดชาร์จ</span><span class="text-white font-bold">฿${amt.toFixed(2)}</span></div>`;
    if (kwh > 0) html += `<div class="flex justify-between"><span class="text-gray-400">เป้าหมาย</span><span class="text-white font-bold">${kwh} kWh</span></div>`;
    if (pricePerKwh > 0 && amt > 0) html += `<div class="flex justify-between"><span class="text-gray-400">ประมาณ kWh</span><span class="text-yellow-300 font-bold">${(amt/pricePerKwh).toFixed(2)} kWh</span></div>`;
    details.innerHTML = html;
    document.getElementById('confirmModal').classList.remove('hidden');
}
function closeModal() { document.getElementById('confirmModal').classList.add('hidden'); }
</script>

<?php capp_foot(); ?>
