<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';

if (capp_auth()) { header('Location: index.php'); exit; }

$error = '';
$mode  = $_GET['mode'] ?? 'login'; // login | register | otp

// ── REGISTER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $fn    = trim($_POST['first_name'] ?? '');
    $ln    = trim($_POST['last_name']  ?? '');
    $phone = trim($_POST['phone']      ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$fn || !$ln || !$phone || !$email || !$pass) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (strlen($pass) < 8) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($pass !== $pass2) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (DB::fetchOne("SELECT id FROM users WHERE email=?", [$email])) {
        $error = 'อีเมลนี้ถูกใช้งานแล้ว';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $otp  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exp  = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $uid  = DB::insert(
            "INSERT INTO users (first_name,last_name,phone,email,password,otp_code,otp_expires_at,is_verified,role)
             VALUES (?,?,?,?,?,?,?,0,'operator')",
            [$fn, $ln, $phone, $email, $hash, $otp, $exp]
        );
        // Create customer record
        DB::insert("INSERT INTO customers (user_id,full_name,phone,email,member_since) VALUES (?,?,?,?,CURDATE())",
            [$uid, "$fn $ln", $phone, $email]);
        // Create wallet
        $custId = DB::fetchOne("SELECT id FROM customers WHERE user_id=?", [$uid])['id'] ?? 0;
        if ($custId) DB::insert("INSERT INTO wallet_accounts (customer_id,balance) VALUES (?,0)", [$custId]);
        // Log OTP (dev mode)
        file_put_contents(APP_BASE . '/otp_log.txt', date('Y-m-d H:i:s') . " | $email | OTP: $otp\n", FILE_APPEND);
        $_SESSION['capp_reg_email'] = $email;
        $_SESSION['capp_reg_otp']   = $otp;
        header('Location: login.php?mode=otp'); exit;
    }
    $mode = 'register';
}

// ── OTP VERIFY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $email = $_SESSION['capp_reg_email'] ?? '';
    $otp   = trim($_POST['otp'] ?? '');
    $user  = DB::fetchOne("SELECT * FROM users WHERE email=? AND otp_code=? AND otp_expires_at > NOW()", [$email, $otp]);
    if ($user) {
        DB::execute("UPDATE users SET is_verified=1, otp_code=NULL WHERE id=?", [$user['id']]);
        $cust = DB::fetchOne("SELECT id FROM customers WHERE user_id=?", [$user['id']]);
        $_SESSION['capp_customer_id']   = $cust['id'];
        $_SESSION['capp_customer_email'] = $email;
        unset($_SESSION['capp_reg_email'], $_SESSION['capp_reg_otp']);
        header('Location: index.php?welcome=1'); exit;
    } else {
        $error = 'รหัส OTP ไม่ถูกต้องหรือหมดอายุแล้ว';
        $mode  = 'otp';
    }
}

// ── RESEND OTP
if (isset($_GET['resend'])) {
    $email = $_SESSION['capp_reg_email'] ?? '';
    if ($email) {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        DB::execute("UPDATE users SET otp_code=?,otp_expires_at=? WHERE email=?", [$otp, $exp, $email]);
        $_SESSION['capp_reg_otp'] = $otp;
        file_put_contents(APP_BASE . '/otp_log.txt', date('Y-m-d H:i:s') . " | RESEND $email | OTP: $otp\n", FILE_APPEND);
    }
    header('Location: login.php?mode=otp&resent=1'); exit;
}

// ── LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $user  = DB::fetchOne("SELECT * FROM users WHERE email=?", [$email]);
    if ($user && $user['is_verified'] && password_verify($pass, $user['password'])) {
        $cust = DB::fetchOne("SELECT id FROM customers WHERE user_id=?", [$user['id']]);
        if (!$cust) {
            // Auto-create customer record if missing
            $cid = DB::insert("INSERT INTO customers (user_id,full_name,phone,email,member_since) VALUES (?,?,?,?,CURDATE())",
                [$user['id'], $user['first_name'].' '.$user['last_name'], $user['phone'], $user['email']]);
            DB::insert("INSERT IGNORE INTO wallet_accounts (customer_id,balance) VALUES (?,0)", [$cid]);
            $cust = ['id' => $cid];
        }
        $_SESSION['capp_customer_id']    = $cust['id'];
        $_SESSION['capp_customer_email'] = $email;
        header('Location: index.php'); exit;
    } elseif ($user && !$user['is_verified']) {
        $error = 'กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ';
    } else {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
    $mode = 'login';
}

capp_head('เข้าสู่ระบบ');
?>
<div class="min-h-screen flex flex-col" style="background:linear-gradient(160deg,#0a1628 0%,#0c1f42 50%,#0f2a55 100%)">

    <!-- Header -->
    <div class="flex-shrink-0 px-6 pt-12 pb-6 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
             style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 8px 32px rgba(245,158,11,.4)">
            <span class="material-icons-round text-3xl text-gray-900">bolt</span>
        </div>
        <h1 class="text-2xl font-bold text-white">⚡ <?= CAPP_NAME ?></h1>
        <p class="text-blue-300 text-sm mt-1">EV Charging Application</p>
    </div>

    <!-- Card -->
    <div class="flex-1 px-5">
        <div class="glass rounded-2xl p-6 card-glow slide-up max-w-sm mx-auto">

            <?php if ($error): ?>
            <div class="flex items-center gap-2 p-3 rounded-xl bg-red-500/20 border border-red-500/40 text-red-300 text-sm mb-4">
                <span class="material-icons-round text-base">error</span> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['resent'])): ?>
            <div class="flex items-center gap-2 p-3 rounded-xl bg-green-500/20 border border-green-500/40 text-green-300 text-sm mb-4">
                <span class="material-icons-round text-base">check_circle</span> ส่ง OTP ใหม่แล้ว
            </div>
            <?php endif; ?>

            <!-- ══ LOGIN ══ -->
            <?php if ($mode === 'login'): ?>
            <h2 class="text-xl font-bold text-white mb-5">เข้าสู่ระบบ</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="text-xs text-blue-300 font-semibold uppercase tracking-wide mb-1.5 block">อีเมล</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">mail</span>
                        <input type="email" name="email" required placeholder="your@email.com"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-blue-300 font-semibold uppercase tracking-wide mb-1.5 block">รหัสผ่าน</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">lock</span>
                        <input type="password" name="password" id="pwdLogin" required placeholder="••••••••"
                               class="input-field w-full pl-10 pr-10 py-3 rounded-xl text-sm">
                        <button type="button" onclick="togglePwd('pwdLogin','eyeLogin')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-400">
                            <span class="material-icons-round text-lg" id="eyeLogin">visibility_off</span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full py-3.5 rounded-xl text-base">
                    เข้าสู่ระบบ
                </button>
            </form>
            <div class="text-center mt-5 text-sm text-gray-400">
                ยังไม่มีบัญชี?
                <a href="?mode=register" class="text-yellow-400 font-semibold ml-1">สมัครสมาชิก</a>
            </div>
            <div class="text-center mt-2">
                <p class="text-xs text-gray-500 mt-3 border-t border-blue-900 pt-3">
                    Demo: <span class="text-blue-300">customer@csms.local</span> / Customer@1234
                </p>
            </div>

            <!-- ══ REGISTER ══ -->
            <?php elseif ($mode === 'register'): ?>
            <h2 class="text-xl font-bold text-white mb-5">สมัครสมาชิก</h2>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="register">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-blue-300 font-semibold mb-1 block">ชื่อ</label>
                        <input type="text" name="first_name" required placeholder="ชื่อ"
                               class="input-field w-full px-3 py-3 rounded-xl text-sm"
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="text-xs text-blue-300 font-semibold mb-1 block">นามสกุล</label>
                        <input type="text" name="last_name" required placeholder="นามสกุล"
                               class="input-field w-full px-3 py-3 rounded-xl text-sm"
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-blue-300 font-semibold mb-1 block">เบอร์มือถือ</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">phone</span>
                        <input type="tel" name="phone" required placeholder="08X-XXX-XXXX"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-blue-300 font-semibold mb-1 block">อีเมล</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">mail</span>
                        <input type="email" name="email" required placeholder="your@email.com"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-blue-300 font-semibold mb-1 block">รหัสผ่าน</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">lock</span>
                        <input type="password" name="password" id="pwdReg" required placeholder="อย่างน้อย 8 ตัวอักษร"
                               class="input-field w-full pl-10 pr-10 py-3 rounded-xl text-sm"
                               oninput="checkPwdStrength(this.value)">
                        <button type="button" onclick="togglePwd('pwdReg','eyeReg')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-400">
                            <span class="material-icons-round text-lg" id="eyeReg">visibility_off</span>
                        </button>
                    </div>
                    <div class="mt-1.5 h-1 rounded-full bg-blue-950" id="strengthBar">
                        <div id="strengthFill" class="h-full rounded-full transition-all" style="width:0%"></div>
                    </div>
                    <p id="strengthLabel" class="text-xs mt-1 text-gray-500"></p>
                </div>
                <div>
                    <label class="text-xs text-blue-300 font-semibold mb-1 block">ยืนยันรหัสผ่าน</label>
                    <div class="relative">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">lock</span>
                        <input type="password" name="password2" required placeholder="••••••••"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full py-3.5 rounded-xl text-base mt-2">
                    สมัครสมาชิก
                </button>
            </form>
            <div class="text-center mt-4 text-sm text-gray-400">
                มีบัญชีแล้ว? <a href="?mode=login" class="text-yellow-400 font-semibold ml-1">เข้าสู่ระบบ</a>
            </div>

            <!-- ══ OTP ══ -->
            <?php elseif ($mode === 'otp'): ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-600/20 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-icons-round text-3xl text-blue-400">mark_email_read</span>
                </div>
                <h2 class="text-xl font-bold text-white">ยืนยัน OTP</h2>
                <p class="text-sm text-gray-400 mt-1">กรอกรหัส 6 หลักที่ส่งไปที่</p>
                <p class="text-blue-300 font-semibold text-sm"><?= htmlspecialchars($_SESSION['capp_reg_email'] ?? '') ?></p>
                <?php
                $devOtp = $_SESSION['capp_reg_otp'] ?? '';
                if ($devOtp): ?>
                <p class="text-xs text-yellow-400 mt-1">[DEV] OTP: <strong><?= $devOtp ?></strong></p>
                <?php endif; ?>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="verify_otp">
                <input type="text" name="otp" maxlength="6" required placeholder="000000"
                       class="input-field w-full text-center text-3xl font-bold tracking-[.5rem] py-4 rounded-xl"
                       autofocus inputmode="numeric" pattern="[0-9]{6}">
                <button type="submit" class="btn-blue w-full py-3.5 rounded-xl text-base">
                    ยืนยัน OTP
                </button>
            </form>
            <div class="text-center mt-4 space-y-2">
                <p class="text-xs text-gray-500" id="otpTimer">รหัสหมดอายุใน <span id="countdown" class="text-yellow-400 font-bold">10:00</span></p>
                <a href="?resend=1" class="text-blue-400 text-sm hover:text-blue-300">ส่งรหัสใหม่อีกครั้ง</a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="text-center py-6 text-xs text-gray-600">
        © <?= date('Y') ?> CSMS · EV Charging System
    </div>
</div>

<script>
function togglePwd(id, eyeId) {
    const i = document.getElementById(id);
    const e = document.getElementById(eyeId);
    if (i.type === 'password') { i.type = 'text'; e.textContent = 'visibility'; }
    else { i.type = 'password'; e.textContent = 'visibility_off'; }
}
function checkPwdStrength(v) {
    const fill = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const colors = ['','#ef4444','#f59e0b','#22c55e','#10b981'];
    const labels = ['','อ่อน','ปานกลาง','แข็งแรง','แข็งแรงมาก'];
    fill.style.width = (score * 25) + '%';
    fill.style.background = colors[score] || '#ef4444';
    label.textContent = labels[score] || '';
    label.style.color = colors[score] || '#ef4444';
}
// OTP Countdown
(function(){
    const el = document.getElementById('countdown');
    if (!el) return;
    let s = 600;
    setInterval(() => {
        s--;
        if (s <= 0) { el.textContent = 'หมดอายุ'; return; }
        el.textContent = Math.floor(s/60)+':'+String(s%60).padStart(2,'0');
    }, 1000);
})();
</script>
<?php capp_foot(); ?>
