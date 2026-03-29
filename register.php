<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['logged_in'])) { header('Location: stations.php'); exit; }

$error = $success = '';
$step  = isset($_SESSION['reg_user_id']) ? 'otp' : 'register';

if ($step === 'otp' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp    = trim(implode('', (array)$_POST['otp']));
    $result = Auth::verifyOtp((int)$_SESSION['reg_user_id'], $otp);
    if ($result['success']) {
        unset($_SESSION['reg_user_id'], $_SESSION['reg_email']);
        header('Location: login.php?verified=1'); exit;
    }
    $error = $result['message'];
}

if ($step === 'otp' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    Auth::resendOtp((int)$_SESSION['reg_user_id']);
    $success = __('otp_resend');
}

if ($step === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = __('password_mismatch');
    } elseif (strlen($_POST['password']) < 8) {
        $error = __('password_min');
    } else {
        $result = Auth::register([
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name']  ?? ''),
            'phone'      => trim($_POST['phone']       ?? ''),
            'email'      => trim($_POST['email']       ?? ''),
            'password'   => $_POST['password'],
        ]);
        if ($result['success']) {
            $_SESSION['reg_user_id'] = $result['user_id'];
            $_SESSION['reg_email']   = trim($_POST['email'] ?? '');
            $step    = 'otp';
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$langMeta = LANG_META[lang()];
?>
<!DOCTYPE html>
<html lang="<?= $langMeta['html'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register') ?> | <?= __('app_name') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Sarabun:wght@400;600;700;800&family=Noto+Sans+SC:wght@400;700&display=swap">
    <style>
        * { font-family:'Sarabun','Inter','Noto Sans SC',sans-serif; }
        body { background:linear-gradient(135deg,#0a1628 0%,#0f2040 50%,#162952 100%); }
        .glass { background:rgba(15,32,64,0.85); backdrop-filter:blur(16px); border:1px solid rgba(59,130,246,0.2); }
        .input-field { background:rgba(10,22,40,0.9); border:1px solid rgba(59,130,246,0.3); color:#e2e8f0; transition:border-color 0.2s; }
        .input-field:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.15); }
        .input-field::placeholder { color:#475569; }
        .btn-primary { background:linear-gradient(135deg,#f59e0b,#d97706); color:#0a1628; font-weight:700; transition:all 0.2s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(245,158,11,0.4); }
        .otp-box { width:48px; height:56px; text-align:center; font-size:1.5rem; font-weight:700; }
        .lang-btn { background:rgba(30,58,110,0.6); border:1px solid rgba(59,130,246,0.3); transition:all 0.2s; }
        .lang-btn:hover, .lang-btn.active { background:rgba(245,158,11,0.15); border-color:#f59e0b; color:#f59e0b; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<?php if ($step === 'register'): ?>
<div class="glass rounded-3xl p-8 w-full max-w-lg shadow-2xl">
    <!-- Language Switcher -->
    <div class="flex justify-end gap-1.5 mb-4">
        <?php foreach (SUPPORTED_LANGS as $code):
            $m = LANG_META[$code]; $ac = lang()===$code?'active':'';
        ?>
        <a href="?lang=<?= $code ?>" class="lang-btn <?= $ac ?> flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs text-gray-300 font-medium">
            <?= $m['flag'] ?> <span class="hidden sm:inline"><?= h($m['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col items-center mb-6">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-xl mb-3">
            <span class="material-icons text-blue-900 text-2xl">person_add</span>
        </div>
        <h1 class="text-xl font-extrabold text-white"><?= __('create_account') ?></h1>
        <p class="text-blue-300 text-xs mt-1"><?= __('app_name') ?> – <?= __('app_subtitle') ?></p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-center gap-2 bg-red-500/20 border border-red-400 text-red-300 rounded-xl px-4 py-3 mb-4 text-sm">
        <span class="material-icons text-lg">error</span><?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-blue-200 mb-1"><?= __('first_name') ?> *</label>
                <input type="text" name="first_name" required value="<?= h($_POST['first_name'] ?? '') ?>"
                    class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-blue-200 mb-1"><?= __('last_name') ?> *</label>
                <input type="text" name="last_name" required value="<?= h($_POST['last_name'] ?? '') ?>"
                    class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-blue-200 mb-1">
                <span class="material-icons text-xs align-middle">phone</span> <?= __('phone') ?> *
            </label>
            <input type="tel" name="phone" required value="<?= h($_POST['phone'] ?? '') ?>"
                class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-blue-200 mb-1">
                <span class="material-icons text-xs align-middle">email</span> <?= __('email') ?> *
            </label>
            <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"
                class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-blue-200 mb-1">
                <span class="material-icons text-xs align-middle">lock</span> <?= __('password') ?> *
            </label>
            <input type="password" name="password" required class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-blue-200 mb-1">
                <span class="material-icons text-xs align-middle">lock_reset</span> <?= __('confirm_password') ?> *
            </label>
            <input type="password" name="confirm_password" required class="input-field w-full rounded-xl px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="btn-primary w-full rounded-xl py-3 text-sm flex items-center justify-center gap-2">
            <span class="material-icons text-lg">how_to_reg</span> <?= __('register') ?>
        </button>
    </form>
    <p class="text-center text-blue-300 text-sm mt-5"><?= __('have_account') ?>
        <a href="login.php" class="text-yellow-400 hover:text-yellow-300 font-semibold"><?= __('sign_in') ?></a>
    </p>
</div>

<?php else: ?>
<!-- OTP Step -->
<div class="glass rounded-3xl p-8 w-full max-w-sm shadow-2xl">
    <!-- Language Switcher -->
    <div class="flex justify-end gap-1.5 mb-4">
        <?php foreach (SUPPORTED_LANGS as $code):
            $m = LANG_META[$code]; $ac = lang()===$code?'active':'';
        ?>
        <a href="?lang=<?= $code ?>" class="lang-btn <?= $ac ?> flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs text-gray-300 font-medium">
            <?= $m['flag'] ?> <span class="hidden sm:inline"><?= h($m['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col items-center mb-6">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-xl mb-3">
            <span class="material-icons text-blue-900 text-2xl">mark_email_read</span>
        </div>
        <h1 class="text-xl font-extrabold text-white"><?= __('otp_verify') ?></h1>
        <p class="text-blue-300 text-xs mt-1 text-center"><?= __('otp_sent_to') ?><br>
            <span class="text-yellow-400 font-medium"><?= h($_SESSION['reg_email'] ?? '') ?></span>
        </p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-center gap-2 bg-red-500/20 border border-red-400 text-red-300 rounded-xl px-4 py-3 mb-4 text-sm">
        <span class="material-icons text-lg">error</span><?= h($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flex items-center gap-2 bg-green-500/20 border border-green-400 text-green-300 rounded-xl px-4 py-3 mb-4 text-sm">
        <span class="material-icons text-lg">check_circle</span><?= h($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="otpForm" class="space-y-5">
        <div class="flex justify-center gap-2">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" name="otp[]" maxlength="1" required
                class="otp-box input-field rounded-xl"
                oninput="otpNext(this,<?= $i ?>)"
                onkeydown="otpBack(event,this,<?= $i ?>)">
            <?php endfor; ?>
        </div>
        <button type="submit" name="otp" class="btn-primary w-full rounded-xl py-3 text-sm flex items-center justify-center gap-2">
            <span class="material-icons">verified</span> <?= __('otp_verify_btn') ?>
        </button>
    </form>
    <form method="POST" class="mt-3">
        <button type="submit" name="resend"
            class="w-full text-center text-blue-300 hover:text-yellow-400 text-sm transition py-2">
            <span class="material-icons text-sm align-middle">refresh</span> <?= __('otp_resend') ?>
        </button>
    </form>
</div>
<?php endif; ?>

<script>
const boxes = document.querySelectorAll('input[name="otp[]"]');
function otpNext(el,idx){ el.value=el.value.replace(/\D/g,''); if(el.value&&idx<5)boxes[idx+1].focus(); const full=[...boxes].map(b=>b.value).join(''); if(full.length===6)setTimeout(()=>document.getElementById('otpForm')?.submit(),300); }
function otpBack(e,el,idx){ if(e.key==='Backspace'&&!el.value&&idx>0)boxes[idx-1].focus(); }
</script>
</body>
</html>
