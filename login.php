<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: stations.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($result['success']) { header('Location: stations.php'); exit; }
    $error = $result['message'];
}

$langMeta = LANG_META[lang()];
?>
<!DOCTYPE html>
<html lang="<?= $langMeta['html'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login') ?> | <?= __('app_name') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Sarabun:wght@400;600;700;800&family=Noto+Sans+SC:wght@400;700&display=swap">
    <style>
        * { font-family:'Sarabun','Inter','Noto Sans SC',sans-serif; }
        body { background: linear-gradient(135deg,#0a1628 0%,#0f2040 50%,#162952 100%); }
        .glass { background:rgba(15,32,64,0.85); backdrop-filter:blur(16px); border:1px solid rgba(59,130,246,0.2); }
        .input-field { background:rgba(10,22,40,0.9); border:1px solid rgba(59,130,246,0.3); color:#e2e8f0; transition:border-color 0.2s; }
        .input-field:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.15); }
        .input-field::placeholder { color:#475569; }
        .btn-login { background:linear-gradient(135deg,#f59e0b,#d97706); color:#0a1628; font-weight:700; transition:all 0.2s; }
        .btn-login:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(245,158,11,0.4); }
        .orb { border-radius:50%; filter:blur(80px); position:absolute; }
        .lang-btn { background:rgba(30,58,110,0.6); border:1px solid rgba(59,130,246,0.3); transition:all 0.2s; }
        .lang-btn:hover, .lang-btn.active { background:rgba(245,158,11,0.15); border-color:#f59e0b; color:#f59e0b; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="orb w-80 h-80 bg-blue-600/20 top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="orb w-96 h-96 bg-yellow-500/10 bottom-0 right-0 translate-x-1/4 translate-y-1/4"></div>

    <div class="glass rounded-3xl p-8 w-full max-w-md relative z-10 shadow-2xl">
        <!-- Language Switcher -->
        <div class="flex justify-end gap-1.5 mb-5">
            <?php foreach (SUPPORTED_LANGS as $code):
                $m = LANG_META[$code];
                $active = lang() === $code ? 'active' : '';
            ?>
            <a href="?lang=<?= $code ?>"
               class="lang-btn <?= $active ?> flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs text-gray-300 font-medium">
                <?= $m['flag'] ?> <span class="hidden sm:inline"><?= h($m['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Logo -->
        <div class="flex flex-col items-center mb-7">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-xl mb-4">
                <span class="material-icons text-blue-900 text-3xl">bolt</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white"><?= __('app_name') ?></h1>
            <p class="text-blue-300 text-sm mt-1"><?= __('app_subtitle') ?></p>
        </div>

        <?php if ($error): ?>
        <div class="flex items-center gap-2 bg-red-500/20 border border-red-400 text-red-300 rounded-xl px-4 py-3 mb-5 text-sm">
            <span class="material-icons text-lg">error</span><span><?= h($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['verified'])): ?>
        <div class="flex items-center gap-2 bg-green-500/20 border border-green-400 text-green-300 rounded-xl px-4 py-3 mb-5 text-sm">
            <span class="material-icons text-lg">check_circle</span><span>ยืนยันอีเมลสำเร็จ กรุณาเข้าสู่ระบบ</span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-blue-200 mb-1.5">
                    <span class="material-icons text-sm align-middle mr-1">email</span><?= __('email') ?>
                </label>
                <input type="email" name="email" required placeholder="your@email.com"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    class="input-field w-full rounded-xl px-4 py-3 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-blue-200 mb-1.5">
                    <span class="material-icons text-sm align-middle mr-1">lock</span><?= __('password') ?>
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" required placeholder="••••••••"
                        class="input-field w-full rounded-xl px-4 py-3 text-sm pr-12">
                    <button type="button" onclick="togglePass()" class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-400 hover:text-yellow-400 transition">
                        <span class="material-icons text-xl" id="eyeIcon">visibility_off</span>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login w-full rounded-xl py-3.5 text-base flex items-center justify-center gap-2 mt-2">
                <span class="material-icons">login</span> <?= __('login') ?>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-blue-300 text-sm"><?= __('no_account') ?>
                <a href="register.php" class="text-yellow-400 hover:text-yellow-300 font-semibold transition"><?= __('sign_up') ?></a>
            </p>
        </div>
    </div>
    <script>
    function togglePass() {
        const p = document.getElementById('password');
        const i = document.getElementById('eyeIcon');
        p.type = p.type === 'password' ? 'text' : 'password';
        i.textContent = p.type === 'password' ? 'visibility_off' : 'visibility';
    }
    </script>
</body>
</html>
