<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId = (int)$_SESSION['user_id'];

$carTypes = DB::fetchAll("SELECT * FROM car_types ORDER BY brand, name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        DB::insert(
            "INSERT INTO customers (user_id,full_name,phone,email,license_plate,car_type_id,member_since,notes)
             VALUES (?,?,?,?,?,?,?,?)",
            [$userId, trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['email']),
             strtoupper(trim($_POST['license_plate'])), $_POST['car_type_id'] ?: null,
             $_POST['member_since'] ?: date('Y-m-d'), trim($_POST['notes'])]
        );
        flash('cust', __('flash_cust_created'), 'success');
        header('Location: customers.php'); exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        DB::execute(
            "UPDATE customers SET full_name=?,phone=?,email=?,license_plate=?,car_type_id=?,notes=?
             WHERE id=? AND user_id=?",
            [trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['email']),
             strtoupper(trim($_POST['license_plate'])), $_POST['car_type_id'] ?: null,
             trim($_POST['notes']), $id, $userId]
        );
        flash('cust', __('flash_cust_updated'), 'success');
        header('Location: customers.php'); exit;
    }

    if ($action === 'delete') {
        DB::execute("DELETE FROM customers WHERE id=? AND user_id=?", [(int)$_POST['id'], $userId]);
        flash('cust', __('flash_cust_deleted'), 'success');
        header('Location: customers.php'); exit;
    }
}

// ── Search & Filter
$search    = trim($_GET['q'] ?? '');
$carTypeF  = (int)($_GET['car_type'] ?? 0);
$sortBy    = in_array($_GET['sort'] ?? '', ['total_spend','total_sessions','total_kwh','member_since']) ? $_GET['sort'] : 'total_spend';
$perPage   = 15;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;

$where  = ["c.user_id=?"];
$params = [$userId];
if ($search) { $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.license_plate LIKE ?)"; $like="%{$search}%"; $params=array_merge($params,[$like,$like,$like]); }
if ($carTypeF) { $where[] = "c.car_type_id=?"; $params[] = $carTypeF; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$total     = (int)(DB::fetchOne("SELECT COUNT(*) AS n FROM customers c $whereStr", $params)['n'] ?? 0);
$customers = DB::fetchAll(
    "SELECT c.*, ct.name AS car_name, ct.brand AS car_brand, ct.icon AS car_icon
     FROM customers c LEFT JOIN car_types ct ON ct.id=c.car_type_id
     $whereStr ORDER BY c.{$sortBy} DESC LIMIT $perPage OFFSET $offset",
    $params
);

// ── Summary stats
$summary = DB::fetchOne(
    "SELECT COUNT(*) AS total, SUM(total_sessions) AS sessions,
            SUM(total_kwh) AS kwh, SUM(total_spend) AS spend
     FROM customers WHERE user_id=?", [$userId]
);

// ── Car type distribution
$carDist = DB::fetchAll(
    "SELECT ct.name, ct.brand, COUNT(c.id) AS cnt
     FROM customers c JOIN car_types ct ON ct.id=c.car_type_id
     WHERE c.user_id=? GROUP BY ct.id ORDER BY cnt DESC", [$userId]
);

// ── Selected customer detail
$viewId  = (int)($_GET['view'] ?? 0);
$viewCust = $viewId ? DB::fetchOne(
    "SELECT c.*, ct.name AS car_name, ct.brand AS car_brand
     FROM customers c LEFT JOIN car_types ct ON ct.id=c.car_type_id
     WHERE c.id=? AND c.user_id=?", [$viewId, $userId]
) : null;
$viewTx = $viewCust ? DB::fetchAll(
    "SELECT t.*, s.name AS station_name
     FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE t.customer_id=? ORDER BY t.start_time DESC LIMIT 20", [$viewId]
) : [];

$pageUrl = "customers.php?q=".urlencode($search)."&car_type={$carTypeF}&sort={$sortBy}";

layoutHead('cust_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('customers.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-3xl">people</span>
                <?= __('cust_title') ?>
            </h2>
            <p class="text-blue-300 text-sm mt-1"><?= __('cust_subtitle') ?></p>
        </div>
        <button onclick="document.getElementById('modalCreate').classList.remove('hidden')"
            class="flex items-center gap-2 bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold px-5 py-2.5 rounded-xl shadow-lg hover:shadow-yellow-500/30 transition">
            <span class="material-icons">person_add</span> <?= __('add_customer') ?>
        </button>
    </div>

    <?= flashAlert('cust') ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $sCards = [
            [__('kpi_total_customers'), number_format($summary['total']),           '',      'people',        'from-blue-600 to-blue-800'],
            [__('kpi_sessions'),        number_format($summary['sessions']),        '',      'bolt',          'from-green-600 to-green-800'],
            [__('kpi_energy'),          number_format($summary['kwh'],1).' kWh',   '',      'electric_meter','from-yellow-500 to-yellow-700'],
            [__('kpi_revenue'),         '฿'.number_format($summary['spend'],2),    '',      'monetization_on','from-purple-600 to-purple-800'],
        ];
        foreach ($sCards as [$lbl,$val,$sub,$icon,$grad]):
        ?>
        <div class="glass-card rounded-2xl p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center flex-shrink-0">
                <span class="material-icons text-white text-xl"><?= $icon ?></span>
            </div>
            <div>
                <p class="text-xs text-blue-300"><?= $lbl ?></p>
                <p class="text-xl font-bold text-white"><?= $val ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- Car Type Sidebar -->
        <div class="glass-card rounded-2xl p-5 h-fit">
            <h3 class="font-bold text-white text-sm mb-3 flex items-center gap-2">
                <span class="material-icons text-yellow-400">directions_car</span> <?= __('car_type_title') ?>
            </h3>
            <div class="space-y-2">
                <a href="customers.php?q=<?= urlencode($search) ?>&sort=<?= $sortBy ?>"
                    class="flex items-center justify-between text-xs py-1.5 px-2 rounded-lg <?= !$carTypeF?'bg-yellow-500/20 text-yellow-400':'text-blue-300 hover:bg-blue-800/40' ?> transition">
                    <span><?= __('all') ?></span>
                    <span class="font-bold"><?= $summary['total'] ?></span>
                </a>
                <?php foreach ($carDist as $cd): ?>
                <a href="customers.php?q=<?= urlencode($search) ?>&car_type=<?= urlencode($cd['name']) ?>&sort=<?= $sortBy ?>"
                    class="flex items-center justify-between text-xs py-1.5 px-2 rounded-lg text-blue-300 hover:bg-blue-800/40 transition">
                    <span class="truncate"><?= h($cd['name']) ?></span>
                    <span class="font-bold text-white ml-2"><?= $cd['cnt'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Table -->
        <div class="lg:col-span-3 space-y-4">
            <!-- Search + Sort Bar -->
            <form method="GET" class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1 relative">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-lg">search</span>
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="<?= __('search') ?>..."
                        class="input-field w-full rounded-xl pl-10 pr-4 py-2.5 text-sm">
                </div>
                <select name="sort" onchange="this.form.submit()" class="input-field rounded-xl px-3 py-2.5 text-sm">
                    <option value="total_spend" <?= $sortBy==='total_spend'?'selected':'' ?>><?= __('top_by_spend') ?></option>
                    <option value="total_sessions" <?= $sortBy==='total_sessions'?'selected':'' ?>><?= __('top_by_sessions') ?></option>
                    <option value="total_kwh" <?= $sortBy==='total_kwh'?'selected':'' ?>><?= __('kpi_energy') ?></option>
                    <option value="member_since" <?= $sortBy==='member_since'?'selected':'' ?>><?= __('cust_member_since') ?></option>
                </select>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-blue-700/60 hover:bg-blue-600/60 text-white text-sm flex items-center gap-1">
                    <span class="material-icons text-base">filter_list</span>
                </button>
                <input type="hidden" name="car_type" value="<?= $carTypeF ?>">
            </form>

            <!-- Customers Table -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-blue-800/50 text-sm text-blue-300">
                    <?= __('found_records', number_format($total)) ?>
                </div>
                <?php if (empty($customers)): ?>
                <div class="py-12 text-center text-blue-400">
                    <span class="material-icons text-5xl">people_outline</span>
                    <p class="mt-2"><?= __('no_customer') ?></p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-blue-800/50 text-xs text-blue-400 uppercase">
                                <th class="px-4 py-3 text-left"><?= __('cust_name') ?></th>
                                <th class="px-4 py-3 text-left"><?= __('cust_car_type') ?></th>
                                <th class="px-4 py-3 text-left"><?= __('cust_license') ?></th>
                                <th class="px-4 py-3 text-right"><?= __('kpi_sessions') ?></th>
                                <th class="px-4 py-3 text-right"><?= __('kpi_energy') ?></th>
                                <th class="px-4 py-3 text-right"><?= __('cust_spend') ?> (฿)</th>
                                <th class="px-4 py-3 text-left"><?= __('cust_member_since') ?></th>
                                <th class="px-4 py-3 text-center"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($customers as $i => $cu): ?>
                        <tr class="table-row border-b border-blue-900/20">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-xs font-bold text-yellow-400 flex-shrink-0">
                                        <?= mb_substr($cu['full_name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <p class="text-white font-medium text-xs"><?= h($cu['full_name']) ?></p>
                                        <p class="text-blue-400 text-xs"><?= h($cu['phone'] ?? '') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1 text-xs">
                                    <span class="material-icons text-blue-400 text-sm">electric_car</span>
                                    <span class="text-blue-200"><?= h($cu['car_name'] ?? '-') ?></span>
                                </div>
                                <p class="text-blue-400 text-xs"><?= h($cu['car_brand'] ?? '') ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="bg-blue-800/50 border border-blue-700 text-blue-200 text-xs px-2 py-0.5 rounded-lg font-mono">
                                    <?= h($cu['license_plate'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-white font-bold"><?= number_format($cu['total_sessions']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right text-green-300 text-xs">
                                <?= number_format($cu['total_kwh'],1) ?> kWh
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-yellow-400 font-bold"><?= number_format($cu['total_spend'],0) ?></span>
                            </td>
                            <td class="px-4 py-3 text-blue-300 text-xs"><?= h($cu['member_since']) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?view=<?= $cu['id'] ?>"
                                        class="p-1.5 rounded-lg bg-blue-700/40 hover:bg-blue-600/50 text-blue-300 hover:text-white transition">
                                        <span class="material-icons text-base">visibility</span>
                                    </a>
                                    <button onclick="openEditCust(<?= htmlspecialchars(json_encode($cu), ENT_QUOTES) ?>)"
                                        class="p-1.5 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 transition">
                                        <span class="material-icons text-base">edit</span>
                                    </button>
                                    <button onclick="deleteCust(<?= $cu['id'] ?>, <?= htmlspecialchars(json_encode($cu['full_name']), ENT_QUOTES) ?>)"
                                        class="p-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 transition">
                                        <span class="material-icons text-base">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total > $perPage): ?>
                <div class="px-5 py-4 border-t border-blue-800/50">
                    <?= paginationLinks($total, $perPage, $page, $pageUrl) ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</div>

<!-- ── View Customer Detail Modal -->
<?php if ($viewCust): ?>
<div class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-blue-800/50 sticky top-0 glass-card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center font-bold text-yellow-400">
                    <?= mb_substr($viewCust['full_name'], 0, 1) ?>
                </div>
                <div>
                    <p class="font-bold text-white"><?= h($viewCust['full_name']) ?></p>
                    <p class="text-blue-300 text-xs"><?= h($viewCust['car_brand'].' '.$viewCust['car_name']) ?> · <?= h($viewCust['license_plate']) ?></p>
                </div>
            </div>
            <a href="customers.php" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></a>
        </div>

        <div class="p-6">
            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mb-5">
                <?php foreach ([
                    ['total_sessions',__('kpi_sessions'),'bolt','from-blue-600 to-blue-800'],
                    ['total_kwh',__('kpi_energy'),'electric_meter','from-green-600 to-green-800'],
                    ['total_spend',__('cust_spend'),'monetization_on','from-yellow-500 to-yellow-700'],
                ] as [$col,$lbl,$icon,$grad]): ?>
                <div class="glass-card rounded-xl p-3 text-center">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br <?= $grad ?> flex items-center justify-center mx-auto mb-2">
                        <span class="material-icons text-white text-base"><?= $icon ?></span>
                    </div>
                    <p class="text-xs text-blue-300"><?= $lbl ?></p>
                    <p class="text-sm font-bold text-white">
                        <?= $col==='total_spend' ? '฿'.number_format($viewCust[$col],2) : number_format($viewCust[$col], $col==='total_kwh'?2:0) ?>
                        <?= $col==='total_kwh' ? ' kWh' : '' ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Contact Info -->
            <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
                <div class="glass-card rounded-xl p-3">
                    <p class="text-blue-400 text-xs mb-0.5"><span class="material-icons text-sm align-middle">phone</span> <?= __('cust_phone') ?></p>
                    <p class="text-white"><?= h($viewCust['phone'] ?? '-') ?></p>
                </div>
                <div class="glass-card rounded-xl p-3">
                    <p class="text-blue-400 text-xs mb-0.5"><span class="material-icons text-sm align-middle">email</span> <?= __('cust_email') ?></p>
                    <p class="text-white text-xs truncate"><?= h($viewCust['email'] ?? '-') ?></p>
                </div>
                <div class="glass-card rounded-xl p-3">
                    <p class="text-blue-400 text-xs mb-0.5"><span class="material-icons text-sm align-middle">electric_car</span> <?= __('cust_car_type') ?></p>
                    <p class="text-white"><?= h($viewCust['car_brand'].' '.$viewCust['car_name']) ?></p>
                </div>
                <div class="glass-card rounded-xl p-3">
                    <p class="text-blue-400 text-xs mb-0.5"><span class="material-icons text-sm align-middle">calendar_today</span> <?= __('cust_member_since') ?></p>
                    <p class="text-white"><?= h($viewCust['member_since']) ?></p>
                </div>
            </div>

            <!-- TX History -->
            <h4 class="font-bold text-white text-sm mb-3 flex items-center gap-1">
                <span class="material-icons text-yellow-400 text-base">receipt_long</span> <?= __('cust_detail') ?>
            </h4>
            <div class="space-y-2 max-h-56 overflow-y-auto">
                <?php foreach ($viewTx as $tx): ?>
                <div class="flex items-center justify-between glass-card rounded-xl px-3 py-2 text-xs">
                    <div>
                        <p class="text-white font-medium"><?= h($tx['station_name']) ?></p>
                        <p class="text-blue-400"><?= formatDateTH($tx['start_time']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-yellow-400 font-bold">฿<?= number_format($tx['actual_amount'],2) ?></p>
                        <p class="text-green-300"><?= number_format($tx['energy_kwh'],2) ?> kWh</p>
                    </div>
                    <?= transactionStatusBadge($tx['status']) ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($viewTx)): ?><p class="text-blue-400 text-center py-4"><?= __('no_data') ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create Modal -->
<div id="modalCreate" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-yellow-400">person_add</span> <?= __('add_customer') ?></h3>
            <button onclick="document.getElementById('modalCreate').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_name') ?> *</label>
                    <input type="text" name="full_name" required class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_phone') ?></label>
                    <input type="tel" name="phone" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_email') ?></label>
                    <input type="email" name="email" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_license') ?></label>
                    <input type="text" name="license_plate" placeholder="กข-1234" class="input-field w-full rounded-xl px-4 py-2.5 text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_member_since') ?></label>
                    <input type="date" name="member_since" value="<?= date('Y-m-d') ?>" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_car_type') ?></label>
                    <select name="car_type_id" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                        <option value=""><?= __('select_car_type') ?></option>
                        <?php foreach ($carTypes as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= h($ct['brand'].' '.$ct['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_notes') ?></label>
                    <textarea name="notes" rows="2" class="input-field w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalCreate').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">save</span> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-yellow-400">edit</span> <?= __('edit') ?></h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editCustId">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_name') ?> *</label>
                    <input type="text" name="full_name" id="editFullName" required class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_phone') ?></label>
                    <input type="tel" name="phone" id="editPhone" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_email') ?></label>
                    <input type="email" name="email" id="editEmail" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_license') ?></label>
                    <input type="text" name="license_plate" id="editLicensePlate" class="input-field w-full rounded-xl px-4 py-2.5 text-sm uppercase">
                </div>
                <div></div>
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_car_type') ?></label>
                    <select name="car_type_id" id="editCarTypeId" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                        <option value=""><?= __('select_car_type') ?></option>
                        <?php foreach ($carTypes as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= h($ct['brand'].' '.$ct['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('cust_notes') ?></label>
                    <textarea name="notes" id="editNotes" rows="2" class="input-field w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">save</span> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<form id="deleteCustForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteCustId">
</form>

<style>
.glass-card { background:rgba(15,32,64,0.7); backdrop-filter:blur(12px); border:1px solid rgba(59,130,246,0.2); }
.input-field { background:rgba(10,22,40,0.9); border:1px solid rgba(59,130,246,0.3); color:#e2e8f0; transition:border-color 0.2s; }
.input-field:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.15); }
.input-field::placeholder { color:#475569; }
.table-row:hover { background:rgba(59,130,246,0.06); }
</style>
<script>
function openEditCust(c) {
    document.getElementById('editCustId').value = c.id;
    document.getElementById('editFullName').value = c.full_name;
    document.getElementById('editPhone').value = c.phone || '';
    document.getElementById('editEmail').value = c.email || '';
    document.getElementById('editLicensePlate').value = c.license_plate || '';
    document.getElementById('editCarTypeId').value = c.car_type_id || '';
    document.getElementById('editNotes').value = c.notes || '';
    document.getElementById('modalEdit').classList.remove('hidden');
}
function deleteCust(id, name) {
    if (confirm('ยืนยันลบลูกค้า "' + name + '"?')) {
        document.getElementById('deleteCustId').value = id;
        document.getElementById('deleteCustForm').submit();
    }
}
</script>
<?php layoutFoot(); ?>
