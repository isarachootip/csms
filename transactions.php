<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId = (int)$_SESSION['user_id'];

$stationId = (int)($_GET['station_id'] ?? 0);
$dateFrom  = $_GET['date_from'] ?? date('Y-m-01');
$dateTo    = $_GET['date_to']   ?? date('Y-m-d');
$statusF   = $_GET['status']    ?? '';
$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;

$where  = ["t.station_id IN (SELECT id FROM stations WHERE user_id=?)"];
$params = [$userId];
if ($stationId) { $where[] = "t.station_id=?"; $params[] = $stationId; }
if ($dateFrom)  { $where[] = "DATE(t.start_time)>=?"; $params[] = $dateFrom; }
if ($dateTo)    { $where[] = "DATE(t.start_time)<=?"; $params[] = $dateTo; }
if ($statusF)   { $where[] = "t.status=?"; $params[] = $statusF; }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM transactions t $whereStr", $params)['c'] ?? 0);
$txList = DB::fetchAll(
    "SELECT t.*, s.name AS station_name, c.serial_number AS charger_serial,
            CONCAT(u.first_name,' ',u.last_name) AS user_name
     FROM transactions t
     JOIN stations s ON s.id=t.station_id
     JOIN chargers c ON c.id=t.charger_id
     JOIN users u ON u.id=t.user_id
     $whereStr ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$summary  = DB::fetchOne(
    "SELECT COUNT(*) AS total_sessions, COALESCE(SUM(energy_kwh),0) AS total_kwh,
            COALESCE(SUM(actual_amount),0) AS total_revenue,
            COALESCE(SUM(estimate_amount),0) AS total_estimate
     FROM transactions t $whereStr", $params
);

$stations = DB::fetchAll("SELECT id,name FROM stations WHERE user_id=? ORDER BY name", [$userId]);
$pageUrl  = "transactions.php?station_id={$stationId}&date_from={$dateFrom}&date_to={$dateTo}&status={$statusF}";

// CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transactions_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', __('col_station_charger'), __('col_start_stop'), __('col_estimate'), __('col_actual'), __('col_kwh'), __('col_status'), __('col_remark')]);
    foreach ($txList as $tx) {
        fputcsv($out, [$tx['id'], $tx['station_name'].' / '.$tx['charger_serial'],
            $tx['start_time'].' – '.($tx['stop_time']??'-'),
            $tx['estimate_amount'], $tx['actual_amount'], $tx['energy_kwh'], $tx['status'], $tx['remark']]);
    }
    fclose($out); exit;
}

layoutHead('tx_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('transactions.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">
    <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
            <span class="material-icons text-yellow-400 text-3xl">receipt_long</span>
            <?= __('tx_title') ?>
        </h2>
        <p class="text-blue-300 text-sm mt-1"><?= __('tx_subtitle') ?></p>
    </div>

    <!-- Filter -->
    <form method="GET" class="glass-card rounded-2xl p-4 mb-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 items-end">
        <div class="col-span-2 md:col-span-1">
            <label class="block text-xs text-blue-300 mb-1"><?= __('station_filter') ?></label>
            <select name="station_id" class="input-field w-full rounded-xl px-3 py-2 text-sm">
                <option value=""><?= __('all') ?></option>
                <?php foreach ($stations as $st): ?>
                <option value="<?= $st['id'] ?>" <?= $stationId==$st['id']?'selected':'' ?>><?= h($st['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-blue-300 mb-1"><?= __('date_from') ?></label>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="input-field w-full rounded-xl px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-blue-300 mb-1"><?= __('date_to') ?></label>
            <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="input-field w-full rounded-xl px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-blue-300 mb-1"><?= __('col_status') ?></label>
            <select name="status" class="input-field w-full rounded-xl px-3 py-2 text-sm">
                <option value=""><?= __('all') ?></option>
                <?php foreach (['Pending','Charging','Completed','Stopped','Faulted'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 flex items-center justify-center gap-1 bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold rounded-xl py-2 text-sm">
                <span class="material-icons text-base">search</span> <?= __('search') ?>
            </button>
            <a href="transactions.php" class="px-3 py-2 rounded-xl border border-blue-700 text-blue-300 text-sm flex items-center">
                <span class="material-icons text-base">refresh</span>
            </a>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $cards = [
            [__('total_sessions'), number_format($summary['total_sessions']), __('minutes')=='min'?'sessions':'ครั้ง', 'bolt',             'from-blue-600 to-blue-800'],
            [__('total_energy'),   number_format($summary['total_kwh'],2),    'kWh',                                   'electric_meter',   'from-green-600 to-green-800'],
            [__('total_revenue'),  number_format($summary['total_revenue'],2),'฿',                                     'monetization_on',  'from-yellow-500 to-yellow-700'],
            [__('total_estimate'), number_format($summary['total_estimate'],2),'฿',                                     'calculate',        'from-purple-600 to-purple-800'],
        ];
        foreach ($cards as [$label, $value, $unit, $icon, $grad]):
        ?>
        <div class="glass-card rounded-2xl p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center flex-shrink-0">
                <span class="material-icons text-white text-xl"><?= $icon ?></span>
            </div>
            <div>
                <p class="text-xs text-blue-300"><?= $label ?></p>
                <p class="text-xl font-bold text-white"><?= $value ?> <span class="text-xs text-blue-300"><?= $unit ?></span></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-blue-800/50 flex items-center justify-between">
            <span class="text-sm text-blue-300"><?= __('found_records', number_format($total)) ?></span>
            <a href="transactions.php?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
               class="flex items-center gap-1 text-xs text-blue-300 hover:text-yellow-400 transition">
                <span class="material-icons text-sm">download</span> <?= __('export_csv') ?>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-blue-800/50 text-xs text-blue-300 uppercase">
                        <th class="px-4 py-3 text-left"><?= __('col_id') ?></th>
                        <th class="px-4 py-3 text-left"><?= __('col_station_charger') ?></th>
                        <th class="px-4 py-3 text-left"><?= __('col_start_stop') ?></th>
                        <th class="px-4 py-3 text-right"><?= __('col_estimate') ?></th>
                        <th class="px-4 py-3 text-right"><?= __('col_actual') ?></th>
                        <th class="px-4 py-3 text-right"><?= __('col_kwh') ?></th>
                        <th class="px-4 py-3 text-center"><?= __('col_status') ?></th>
                        <th class="px-4 py-3 text-left"><?= __('col_remark') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($txList)): ?>
                    <tr><td colspan="8" class="text-center py-10 text-blue-400">
                        <span class="material-icons text-4xl block mb-2">receipt_long</span><?= __('no_data') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($txList as $tx): ?>
                    <tr class="table-row border-b border-blue-900/30">
                        <td class="px-4 py-3 text-blue-300 font-mono text-xs">#<?= $tx['id'] ?></td>
                        <td class="px-4 py-3">
                            <p class="text-white font-medium text-xs"><?= h($tx['station_name']) ?></p>
                            <p class="text-blue-400 text-xs"><?= h($tx['charger_serial']) ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-white text-xs"><?= formatDateTH($tx['start_time']) ?></p>
                            <p class="text-blue-400 text-xs"><?= $tx['stop_time'] ? formatDateTH($tx['stop_time']) : '–' ?></p>
                            <?php if ($tx['duration_minutes']): ?>
                            <p class="text-blue-500 text-xs"><?= $tx['duration_minutes'] ?> <?= __('minutes') ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-blue-200 text-xs"><?= number_format($tx['estimate_amount'],2) ?></td>
                        <td class="px-4 py-3 text-right"><span class="text-white font-semibold"><?= number_format($tx['actual_amount'],2) ?></span></td>
                        <td class="px-4 py-3 text-right text-green-300 text-xs"><?= number_format($tx['energy_kwh'],3) ?></td>
                        <td class="px-4 py-3 text-center"><?= transactionStatusBadge($tx['status']) ?></td>
                        <td class="px-4 py-3 text-blue-300 text-xs max-w-32 truncate"><?= h($tx['remark'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > $perPage): ?>
        <div class="px-5 py-4 border-t border-blue-800/50">
            <?= paginationLinks($total, $perPage, $page, $pageUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>
<?php layoutFoot(); ?>
