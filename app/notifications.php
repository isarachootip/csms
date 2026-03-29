<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid = capp_customer_id();

// Mark all as read
if (isset($_POST['mark_all'])) {
    DB::execute("UPDATE customer_notifications SET read_at=NOW() WHERE customer_id=? AND read_at IS NULL", [$cid]);
    header('Location: notifications.php'); exit;
}
if (isset($_GET['mark'])) {
    DB::execute("UPDATE customer_notifications SET read_at=NOW() WHERE id=? AND customer_id=?",
        [(int)$_GET['mark'], $cid]);
    header('Location: notifications.php'); exit;
}

$notifs = DB::fetchAll(
    "SELECT * FROM customer_notifications WHERE customer_id=? ORDER BY created_at DESC LIMIT 50",
    [$cid]
);
$unread = count(array_filter($notifs, fn($n) => !$n['read_at']));

capp_head('การแจ้งเตือน');
?>

<div class="page-content">
    <div class="top-bar px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="index.php" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-blue-800/50">
                <span class="material-icons-round text-gray-300">arrow_back</span>
            </a>
            <h1 class="text-base font-bold text-white">การแจ้งเตือน</h1>
            <?php if ($unread > 0): ?>
            <span class="badge-pill bg-red-500/20 text-red-300 border border-red-500/30 text-xs"><?= $unread ?> ใหม่</span>
            <?php endif; ?>
        </div>
        <?php if ($unread > 0): ?>
        <form method="POST">
            <button type="submit" name="mark_all" class="text-xs text-blue-400 hover:text-blue-300">อ่านทั้งหมด</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="px-4 pt-3 space-y-2">
        <?php if (empty($notifs)): ?>
        <div class="glass rounded-2xl py-20 text-center">
            <span class="material-icons-round text-5xl text-gray-600 block mb-3">notifications_none</span>
            <p class="text-gray-400">ยังไม่มีการแจ้งเตือน</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifs as $n): ?>
        <?php
            $isNew  = !$n['read_at'];
            $colors = [
                'session' => ['icon'=>'bolt','bg'=>'bg-blue-900/40','color'=>'text-blue-400'],
                'wallet'  => ['icon'=>'account_balance_wallet','bg'=>'bg-green-900/30','color'=>'text-green-400'],
                'promo'   => ['icon'=>'local_offer','bg'=>'bg-yellow-900/30','color'=>'text-yellow-400'],
                'alert'   => ['icon'=>'warning','bg'=>'bg-red-900/30','color'=>'text-red-400'],
                'system'  => ['icon'=>'info','bg'=>'bg-purple-900/30','color'=>'text-purple-400'],
            ];
            $style = $colors[$n['type']] ?? $colors['system'];
            $icon  = $n['icon'] ?? $style['icon'];
        ?>
        <a href="?mark=<?= $n['id'] ?>" class="block">
            <div class="glass rounded-xl px-4 py-3 flex items-start gap-3 transition
                        <?= $isNew ? 'border-blue-500/30 bg-blue-900/10' : '' ?>">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 <?= $style['bg'] ?>">
                    <span class="material-icons-round text-base <?= $style['color'] ?>"><?= $icon ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold <?= $isNew?'text-white':'text-gray-300' ?>">
                            <?= htmlspecialchars($n['title']) ?>
                        </p>
                        <?php if ($isNew): ?>
                        <span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0 mt-1.5"></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($n['body']): ?>
                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-2"><?= htmlspecialchars($n['body']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-600 mt-1"><?= ago($n['created_at']) ?></p>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php capp_bottom_nav('profile'); ?>
<?php capp_foot(); ?>
