<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid  = capp_customer_id();
$cust = capp_customer();

$carTypes = DB::fetchAll("SELECT * FROM car_types ORDER BY brand, name");
$vehicles = DB::fetchAll(
    "SELECT cv.*, ct.name AS car_name, ct.brand, ct.connector_type, ct.battery_kwh
     FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id
     WHERE cv.customer_id=? ORDER BY cv.is_default DESC, cv.id",
    [$cid]
);

// ── POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name']  ?? '');
        $ph = trim($_POST['phone']      ?? '');
        if ($fn && $ln) {
            $fullName = "$fn $ln";
            DB::execute("UPDATE customers SET full_name=?,phone=? WHERE id=?", [$fullName,$ph,$cid]);
            DB::execute("UPDATE users SET first_name=?,last_name=?,phone=? WHERE id=(SELECT user_id FROM customers WHERE id=?)",
                [$fn,$ln,$ph,$cid]);
            capp_flash('profile','อัปเดตโปรไฟล์สำเร็จ');
        }
        header('Location: profile.php'); exit;
    }

    if ($action === 'change_password') {
        $old  = $_POST['old_pass']  ?? '';
        $new  = $_POST['new_pass']  ?? '';
        $new2 = $_POST['new_pass2'] ?? '';
        $user = DB::fetchOne("SELECT * FROM users WHERE id=(SELECT user_id FROM customers WHERE id=?)", [$cid]);
        if (!password_verify($old, $user['password'])) {
            capp_flash('profile','รหัสผ่านเดิมไม่ถูกต้อง','error');
        } elseif (strlen($new) < 8) {
            capp_flash('profile','รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร','error');
        } elseif ($new !== $new2) {
            capp_flash('profile','รหัสผ่านใหม่ไม่ตรงกัน','error');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            DB::execute("UPDATE users SET password=? WHERE id=?", [$hash, $user['id']]);
            capp_flash('profile','เปลี่ยนรหัสผ่านสำเร็จ');
        }
        header('Location: profile.php'); exit;
    }

    if ($action === 'add_vehicle') {
        $ctId  = (int)($_POST['car_type_id'] ?? 0);
        $plate = strtoupper(trim($_POST['license_plate'] ?? ''));
        $nick  = trim($_POST['nickname'] ?? '');
        $setDef = (int)($_POST['is_default'] ?? 0);
        if ($plate) {
            if ($setDef) DB::execute("UPDATE customer_vehicles SET is_default=0 WHERE customer_id=?", [$cid]);
            DB::insert("INSERT INTO customer_vehicles (customer_id,car_type_id,license_plate,nickname,is_default) VALUES (?,?,?,?,?)",
                [$cid, $ctId ?: null, $plate, $nick, $setDef]);
            capp_flash('profile','เพิ่มรถสำเร็จ');
        }
        header('Location: profile.php#vehicles'); exit;
    }

    if ($action === 'set_default_vehicle') {
        $vid = (int)($_POST['vehicle_id'] ?? 0);
        DB::execute("UPDATE customer_vehicles SET is_default=0 WHERE customer_id=?", [$cid]);
        DB::execute("UPDATE customer_vehicles SET is_default=1 WHERE id=? AND customer_id=?", [$vid,$cid]);
        header('Location: profile.php#vehicles'); exit;
    }

    if ($action === 'delete_vehicle') {
        $vid = (int)($_POST['vehicle_id'] ?? 0);
        DB::execute("DELETE FROM customer_vehicles WHERE id=? AND customer_id=?", [$vid,$cid]);
        header('Location: profile.php#vehicles'); exit;
    }
}

// Reload after update
$cust = null; // clear cache

$custFresh = DB::fetchOne("SELECT c.*, w.balance AS wallet_balance FROM customers c LEFT JOIN wallet_accounts w ON w.customer_id=c.id WHERE c.id=?", [$cid]);
$vehicles = DB::fetchAll("SELECT cv.*, ct.name AS car_name, ct.brand, ct.connector_type, ct.battery_kwh FROM customer_vehicles cv LEFT JOIN car_types ct ON ct.id=cv.car_type_id WHERE cv.customer_id=? ORDER BY cv.is_default DESC, cv.id", [$cid]);
$userRow = DB::fetchOne("SELECT * FROM users WHERE id=(SELECT user_id FROM customers WHERE id=?)", [$cid]);
$tier = tier_info((float)($custFresh['total_spend'] ?? 0));

$flash = capp_flash_html('profile');
capp_head('โปรไฟล์ของฉัน');
?>

<div class="page-content">
    <?php capp_top_bar('โปรไฟล์', false,
        "<a href='logout.php' class='w-9 h-9 flex items-center justify-center rounded-full hover:bg-red-900/40 transition'>
            <span class='material-icons-round text-red-400'>logout</span>
        </a>"); ?>

    <div class="px-4 pt-3 space-y-4">
        <?= $flash ?>

        <!-- ── Profile Hero ── -->
        <div class="glass rounded-2xl p-5 card-glow">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-2xl font-bold text-gray-900 flex-shrink-0"
                     style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                    <?= mb_substr($custFresh['full_name'] ?? 'U', 0, 1) ?>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($custFresh['full_name'] ?? '') ?></h2>
                    <p class="text-sm text-gray-400"><?= htmlspecialchars($userRow['email'] ?? '') ?></p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge-pill bg-gradient-to-r <?= $tier[2] ?> text-white text-xs">
                            <?= $tier[1] ?> <?= $tier[0] ?>
                        </span>
                        <?php if ($userRow['is_verified']): ?>
                        <span class="badge-pill bg-green-500/20 text-green-300 border border-green-500/30 text-xs">✅ ยืนยันแล้ว</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center text-sm">
                <div class="bg-blue-900/40 rounded-xl py-2.5">
                    <p class="font-bold text-blue-300"><?= number_format((int)$custFresh['total_sessions']) ?></p>
                    <p class="text-xs text-gray-500">ครั้งชาร์จ</p>
                </div>
                <div class="bg-yellow-900/30 rounded-xl py-2.5">
                    <p class="font-bold text-yellow-300"><?= fmt_kwh((float)$custFresh['total_kwh']) ?></p>
                    <p class="text-xs text-gray-500">kWh</p>
                </div>
                <div class="bg-green-900/30 rounded-xl py-2.5">
                    <p class="font-bold text-green-300">฿<?= number_format((float)$custFresh['total_spend'],0) ?></p>
                    <p class="text-xs text-gray-500">ใช้จ่ายรวม</p>
                </div>
            </div>

            <!-- Tier Progress -->
            <?php
            $nextTier = ['Gold','Platinum'];
            $thresholds = [1000=>['Gold','🥇'],5000=>['Gold','🥇'],20000=>['Platinum','💎']];
            $spend = (float)$custFresh['total_spend'];
            $nextThresh = 0; $nextLabel='';
            if ($spend < 1000) { $nextThresh=1000; $nextLabel='Silver 🥈'; }
            elseif ($spend < 5000) { $nextThresh=5000; $nextLabel='Gold 🥇'; }
            elseif ($spend < 20000) { $nextThresh=20000; $nextLabel='Platinum 💎'; }
            ?>
            <?php if ($nextThresh): ?>
            <div class="mt-3">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>อีก ฿<?= number_format($nextThresh-$spend,0) ?> → <?= $nextLabel ?></span>
                    <span><?= round($spend/$nextThresh*100) ?>%</span>
                </div>
                <div class="h-2 bg-blue-950 rounded-full">
                    <div class="h-full rounded-full" style="width:<?= min(100,round($spend/$nextThresh*100)) ?>%;background:linear-gradient(90deg,#f59e0b,#d97706)"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Wallet Quick ── -->
        <a href="wallet.php">
            <div class="glass rounded-2xl px-5 py-4 flex items-center gap-3 hover:border-yellow-500/40 transition">
                <span class="material-icons-round text-yellow-400 text-xl">account_balance_wallet</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-white">Wallet</p>
                    <p class="text-xs text-gray-400">ดูยอดเงินและเติมเงิน</p>
                </div>
                <p class="text-lg font-bold text-yellow-400">฿<?= number_format((float)($custFresh['wallet_balance']??0),2) ?></p>
                <span class="material-icons-round text-gray-500">chevron_right</span>
            </div>
        </a>

        <!-- ── Vehicles ── -->
        <div id="vehicles">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-white flex items-center gap-1">
                    <span class="material-icons-round text-blue-400 text-base">directions_car</span>
                    รถของฉัน
                </p>
                <button onclick="document.getElementById('addVehicleModal').classList.remove('hidden')"
                        class="text-xs text-yellow-400 flex items-center gap-1">
                    <span class="material-icons-round text-sm">add</span> เพิ่มรถ
                </button>
            </div>
            <div class="space-y-2">
                <?php foreach ($vehicles as $v): ?>
                <div class="glass rounded-xl p-3 flex items-center gap-3 <?= $v['is_default']?'border-yellow-500/30':'' ?>">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0
                                <?= $v['is_default']?'bg-yellow-900/30':'bg-blue-900/30' ?>">🚗</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white"><?= htmlspecialchars($v['car_name'] ?? 'รถของฉัน') ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($v['license_plate']) ?>
                            <?= $v['nickname'] ? '· '.htmlspecialchars($v['nickname']) : '' ?></p>
                        <?php if ($v['connector_type']): ?>
                        <span class="connector-badge border-blue-700/50 text-blue-300 mt-0.5">
                            <?= connector_icon($v['connector_type']) ?> <?= $v['connector_type'] ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($v['is_default']): ?>
                        <span class="text-xs text-yellow-400 ml-1 font-semibold">★ รถหลัก</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col gap-1">
                        <?php if (!$v['is_default']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="set_default_vehicle">
                            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="text-xs text-yellow-400 whitespace-nowrap">ตั้งค่าหลัก</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('ลบรถนี้?')">
                            <input type="hidden" name="action" value="delete_vehicle">
                            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="text-xs text-red-400">ลบ</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($vehicles)): ?>
                <div class="glass rounded-xl py-8 text-center text-gray-500 text-sm">
                    <span class="material-icons-round text-3xl block mb-2">directions_car</span>
                    ยังไม่มีรถในระบบ — เพิ่มรถแรกของคุณ!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Edit Profile ── -->
        <div class="glass rounded-2xl p-4 card-glow">
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <span class="material-icons-round text-blue-400">edit</span>
                แก้ไขโปรไฟล์
            </p>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="update_profile">
                <?php
                $nameParts = explode(' ', $custFresh['full_name'] ?? '', 2);
                $fn = $nameParts[0] ?? '';
                $ln = $nameParts[1] ?? '';
                ?>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">ชื่อ</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($fn) ?>"
                               class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">นามสกุล</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($ln) ?>"
                               class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">เบอร์มือถือ</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($custFresh['phone'] ?? '') ?>"
                           class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <button type="submit" class="btn-blue w-full py-3 rounded-xl text-sm">บันทึกโปรไฟล์</button>
            </form>
        </div>

        <!-- ── Change Password ── -->
        <div class="glass rounded-2xl p-4">
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <span class="material-icons-round text-yellow-400">lock</span>
                เปลี่ยนรหัสผ่าน
            </p>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="change_password">
                <?php foreach (['old_pass'=>'รหัสผ่านเดิม','new_pass'=>'รหัสผ่านใหม่','new_pass2'=>'ยืนยันรหัสผ่านใหม่'] as $n=>$l): ?>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $l ?></label>
                    <input type="password" name="<?= $n ?>" placeholder="••••••••"
                           class="input-field w-full px-3 py-2.5 rounded-xl text-sm" required>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-blue w-full py-3 rounded-xl text-sm">เปลี่ยนรหัสผ่าน</button>
            </form>
        </div>

        <!-- ── App Info ── -->
        <div class="glass rounded-2xl p-4 space-y-3">
            <a href="notifications.php" class="flex items-center gap-3 py-1">
                <span class="material-icons-round text-blue-400">notifications</span>
                <span class="text-sm text-white flex-1">การแจ้งเตือน</span>
                <span class="material-icons-round text-gray-500">chevron_right</span>
            </a>
            <a href="../" class="flex items-center gap-3 py-1">
                <span class="material-icons-round text-gray-400">admin_panel_settings</span>
                <span class="text-sm text-white flex-1">ระบบจัดการ (Admin)</span>
                <span class="material-icons-round text-gray-500">chevron_right</span>
            </a>
            <div class="flex items-center gap-3 py-1">
                <span class="material-icons-round text-gray-500">info</span>
                <span class="text-sm text-gray-400 flex-1">เวอร์ชัน</span>
                <span class="text-xs text-gray-500">MVP 1.0</span>
            </div>
        </div>

        <!-- Logout -->
        <a href="logout.php" class="block">
            <div class="btn-red w-full py-4 rounded-2xl text-center font-bold flex items-center justify-center gap-2">
                <span class="material-icons-round">logout</span>
                ออกจากระบบ
            </div>
        </a>
    </div>
</div>

<!-- ── Add Vehicle Modal ── -->
<div id="addVehicleModal" class="fixed inset-0 z-50 hidden flex items-end justify-center"
     style="background:rgba(0,0,0,.6);backdrop-filter:blur(8px)">
    <div class="glass rounded-t-2xl w-full max-w-lg p-5 pb-8 slide-up" style="max-height:85vh;overflow-y:auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-white">เพิ่มรถใหม่</h3>
            <button onclick="document.getElementById('addVehicleModal').classList.add('hidden')"
                    class="text-gray-400"><span class="material-icons-round">close</span></button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="add_vehicle">
            <div>
                <label class="text-xs text-gray-400 mb-1 block">ยี่ห้อ/รุ่นรถ</label>
                <select name="car_type_id" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                    <option value="">-- เลือกรุ่นรถ --</option>
                    <?php foreach ($carTypes as $ct): ?>
                    <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['brand'].' '.$ct['name']) ?> (<?= $ct['connector_type'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">ทะเบียนรถ <span class="text-red-400">*</span></label>
                <input type="text" name="license_plate" required placeholder="กข-1234"
                       class="input-field w-full px-3 py-2.5 rounded-xl text-sm" style="text-transform:uppercase">
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">ชื่อเล่น (ไม่บังคับ)</label>
                <input type="text" name="nickname" placeholder="เช่น รถที่ทำงาน"
                       class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_default" value="1" class="accent-yellow-400 w-4 h-4">
                <span class="text-sm text-gray-300">ตั้งเป็นรถหลัก</span>
            </label>
            <button type="submit" class="btn-primary w-full py-3 rounded-xl">เพิ่มรถ</button>
        </form>
    </div>
</div>

<?php capp_bottom_nav('profile'); ?>
<?php capp_foot(); ?>
