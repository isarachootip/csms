<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId = (int)$_SESSION['user_id'];

$today     = date('Y-m-d');
$monthStart= date('Y-m-01');
$yearStart = date('Y-01-01');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$lastMonthS= date('Y-m-01', strtotime('-1 month'));
$lastMonthE= date('Y-m-t', strtotime('-1 month'));
$lastYearS = date('Y-01-01', strtotime('-1 year'));
$lastYearE = date('Y-12-31', strtotime('-1 year'));

// ── Helper: fetch KPI for date range
function fetchKPI(int $userId, string $from, string $to): array {
    $row = DB::fetchOne(
        "SELECT COUNT(*) AS sessions,
                COALESCE(SUM(t.actual_amount),0) AS revenue,
                COALESCE(SUM(t.energy_kwh),0) AS kwh,
                COUNT(DISTINCT t.customer_id) AS customers
         FROM transactions t
         JOIN stations s ON s.id=t.station_id
         WHERE s.user_id=? AND DATE(t.start_time) BETWEEN ? AND ?
           AND t.status IN ('Completed','Stopped','Charging')",
        [$userId, $from, $to]
    );
    $sessions = (int)$row['sessions'];
    return [
        'sessions'  => $sessions,
        'revenue'   => (float)$row['revenue'],
        'kwh'       => (float)$row['kwh'],
        'customers' => (int)$row['customers'],
        'avg_rev'   => $sessions > 0 ? round($row['revenue'] / $sessions, 2) : 0,
        'avg_kwh'   => $sessions > 0 ? round($row['kwh'] / $sessions, 3) : 0,
    ];
}

// ── Periods
$daily    = fetchKPI($userId, $today,      $today);
$yesterday_kpi = fetchKPI($userId, $yesterday, $yesterday);
$mtd      = fetchKPI($userId, $monthStart, $today);
$lastMtd  = fetchKPI($userId, $lastMonthS, $lastMonthE);
$ytd      = fetchKPI($userId, $yearStart,  $today);
$lastYtd  = fetchKPI($userId, $lastYearS,  $lastYearE);

// ── Active now
$activeNow = (int)(DB::fetchOne(
    "SELECT COUNT(*) AS c FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND t.status='Charging'", [$userId]
)['c'] ?? 0);

// ── Charger status counts
$chargerStatus = DB::fetchAll(
    "SELECT c.controller_status, COUNT(*) AS cnt
     FROM chargers c JOIN stations s ON s.id=c.station_id
     WHERE s.user_id=? GROUP BY c.controller_status", [$userId]
);
$statusMap = ['Online'=>0,'Offline'=>0,'Faulted'=>0,'Updating'=>0];
foreach ($chargerStatus as $r) $statusMap[$r['controller_status']] = (int)$r['cnt'];
$totalChargers = array_sum($statusMap);

// ── Total customers & new this month
$totalCustomers = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM customers WHERE user_id=?", [$userId])['c'] ?? 0);
$newCustomers   = (int)(DB::fetchOne(
    "SELECT COUNT(*) AS c FROM customers WHERE user_id=? AND member_since >= ?",
    [$userId, $monthStart]
)['c'] ?? 0);

// ── Daily revenue last 30 days (chart)
$daily30 = DB::fetchAll(
    "SELECT DATE(t.start_time) AS d, SUM(t.actual_amount) AS rev, COUNT(*) AS sess
     FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND t.start_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND t.status IN ('Completed','Stopped')
     GROUP BY DATE(t.start_time) ORDER BY d",
    [$userId]
);
// Fill all 30 days
$daily30Map = [];
foreach ($daily30 as $r) $daily30Map[$r['d']] = $r;
$chartDays = $chartRev = $chartSess = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartDays[] = date('d/m', strtotime($d));
    $chartRev[]  = isset($daily30Map[$d]) ? (float)$daily30Map[$d]['rev']  : 0;
    $chartSess[] = isset($daily30Map[$d]) ? (int)$daily30Map[$d]['sess'] : 0;
}

// ── Monthly revenue YTD (chart)
$monthly = DB::fetchAll(
    "SELECT MONTH(t.start_time) AS m, SUM(t.actual_amount) AS rev, COUNT(*) AS sess
     FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND YEAR(t.start_time)=YEAR(CURDATE())
       AND t.status IN ('Completed','Stopped')
     GROUP BY MONTH(t.start_time) ORDER BY m",
    [$userId]
);
$monthMap = [];
foreach ($monthly as $r) $monthMap[(int)$r['m']] = $r;
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartMonthRev = $chartMonthSess = [];
for ($m = 1; $m <= 12; $m++) {
    $chartMonthRev[]  = isset($monthMap[$m]) ? (float)$monthMap[$m]['rev']  : 0;
    $chartMonthSess[] = isset($monthMap[$m]) ? (int)$monthMap[$m]['sess'] : 0;
}

// ── Revenue by station (pie)
$byStation = DB::fetchAll(
    "SELECT s.name, SUM(t.actual_amount) AS rev, COUNT(*) AS sess
     FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND YEAR(t.start_time)=YEAR(CURDATE())
       AND t.status IN ('Completed','Stopped')
     GROUP BY s.id ORDER BY rev DESC", [$userId]
);

// ── Car type breakdown (donut)
$carTypes = DB::fetchAll(
    "SELECT ct.name, ct.brand, COUNT(*) AS sessions, SUM(t.actual_amount) AS revenue
     FROM transactions t
     JOIN car_types ct ON ct.id=t.car_type_id
     JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND t.status IN ('Completed','Stopped')
     GROUP BY ct.id ORDER BY sessions DESC LIMIT 10", [$userId]
);

// ── Hourly heatmap (this month)
$hourly = DB::fetchAll(
    "SELECT HOUR(t.start_time) AS hr, COUNT(*) AS sess
     FROM transactions t JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND DATE(t.start_time) BETWEEN ? AND ?
       AND t.status IN ('Completed','Stopped')
     GROUP BY HOUR(t.start_time) ORDER BY hr",
    [$userId, $monthStart, $today]
);
$hourMap = [];
foreach ($hourly as $r) $hourMap[(int)$r['hr']] = (int)$r['sess'];
$chartHourSess = [];
for ($h = 0; $h < 24; $h++) $chartHourSess[] = $hourMap[$h] ?? 0;

// ── Top customers (MTD)
$topCustomers = DB::fetchAll(
    "SELECT cu.full_name, cu.license_plate, ct.name AS car_name,
            COUNT(*) AS sessions, SUM(t.actual_amount) AS spend, SUM(t.energy_kwh) AS kwh
     FROM transactions t
     JOIN customers cu ON cu.id=t.customer_id
     LEFT JOIN car_types ct ON ct.id=t.car_type_id
     JOIN stations s ON s.id=t.station_id
     WHERE s.user_id=? AND DATE(t.start_time) BETWEEN ? AND ?
       AND t.status IN ('Completed','Stopped')
     GROUP BY t.customer_id ORDER BY spend DESC LIMIT 8",
    [$userId, $monthStart, $today]
);

// ── Recent sessions (last 10)
$recentSessions = DB::fetchAll(
    "SELECT t.*, s.name AS station_name, c.serial_number AS sn,
            cu.full_name AS cust_name, cu.license_plate,
            ct.name AS car_name
     FROM transactions t
     JOIN stations s ON s.id=t.station_id
     JOIN chargers c ON c.id=t.charger_id
     LEFT JOIN customers cu ON cu.id=t.customer_id
     LEFT JOIN car_types ct ON ct.id=t.car_type_id
     WHERE s.user_id=?
     ORDER BY t.created_at DESC LIMIT 10", [$userId]
);

// ── % change helper
function pctChange(float $current, float $prev): string {
    if ($prev == 0) return $current > 0 ? '<span class="text-green-400 text-xs">+NEW</span>' : '<span class="text-gray-500 text-xs">–</span>';
    $pct = (($current - $prev) / $prev) * 100;
    $cls = $pct >= 0 ? 'text-green-400' : 'text-red-400';
    $arrow = $pct >= 0 ? '▲' : '▼';
    return "<span class=\"{$cls} text-xs font-semibold\">{$arrow} " . abs(round($pct, 1)) . "%</span>";
}

layoutHead('dash_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('dashboard.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">

    <!-- ── Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-3xl">dashboard</span>
                <?= __('dash_title') ?>
            </h2>
            <p class="text-blue-300 text-sm mt-1"><?= __('dash_subtitle') ?> · <?= date('d M Y') ?></p>
        </div>
        <!-- Live Indicator -->
        <div class="flex items-center gap-2 bg-green-500/10 border border-green-500/30 rounded-xl px-4 py-2">
            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
            <span class="text-green-300 text-sm font-medium"><?= $activeNow ?> <?= __('kpi_active_now') ?></span>
        </div>
    </div>

    <!-- ── Period Tabs -->
    <div class="flex gap-2 mb-6 bg-blue-900/40 rounded-2xl p-1.5 w-fit">
        <?php foreach (['daily'=>__('period_daily'),'mtd'=>__('period_mtd'),'ytd'=>__('period_ytd')] as $k=>$label): ?>
        <button onclick="showPeriod('<?= $k ?>')" id="tab_<?= $k ?>"
            class="period-tab px-4 py-2 rounded-xl text-sm font-semibold transition-all">
            <?= $label ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── DAILY KPIs -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="period_daily">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <?php
            $dKPIs = [
                [__('kpi_revenue'),     '฿'.number_format($daily['revenue'],2),    pctChange($daily['revenue'],  $yesterday_kpi['revenue']),    'payments',         'from-yellow-500 to-yellow-700'],
                [__('kpi_sessions'),    number_format($daily['sessions']),          pctChange($daily['sessions'], $yesterday_kpi['sessions']),    'bolt',             'from-blue-600 to-blue-800'],
                [__('kpi_energy'),      number_format($daily['kwh'],1).' kWh',     pctChange($daily['kwh'],      $yesterday_kpi['kwh']),         'electric_meter',   'from-green-600 to-green-800'],
                [__('kpi_customers'),   $daily['customers'],                        pctChange($daily['customers'],$yesterday_kpi['customers']),   'people',           'from-purple-600 to-purple-800'],
                [__('kpi_avg_session'), '฿'.number_format($daily['avg_rev'],0),    pctChange($daily['avg_rev'],  $yesterday_kpi['avg_rev']),     'trending_up',      'from-teal-600 to-teal-800'],
                [__('kpi_avg_kwh'),     number_format($daily['avg_kwh'],1).' kWh', pctChange($daily['avg_kwh'],  $yesterday_kpi['avg_kwh']),     'electric_bolt',    'from-indigo-600 to-indigo-800'],
            ];
            foreach ($dKPIs as [$lbl,$val,$chg,$icon,$grad]):
            ?>
            <div class="glass-card rounded-2xl p-4">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center mb-3">
                    <span class="material-icons text-white text-lg"><?= $icon ?></span>
                </div>
                <p class="text-xs text-blue-300"><?= $lbl ?></p>
                <p class="text-xl font-bold text-white mt-0.5"><?= $val ?></p>
                <div class="mt-1 flex items-center gap-1">
                    <?= $chg ?>
                    <span class="text-blue-500 text-xs"><?= __('vs_yesterday') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── MTD KPIs -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="period_mtd" class="hidden">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <?php
            $mKPIs = [
                [__('kpi_revenue'),     '฿'.number_format($mtd['revenue'],2),      pctChange($mtd['revenue'],    $lastMtd['revenue']),    'payments',       'from-yellow-500 to-yellow-700'],
                [__('kpi_sessions'),    number_format($mtd['sessions']),            pctChange($mtd['sessions'],   $lastMtd['sessions']),   'bolt',           'from-blue-600 to-blue-800'],
                [__('kpi_energy'),      number_format($mtd['kwh'],1).' kWh',       pctChange($mtd['kwh'],        $lastMtd['kwh']),        'electric_meter', 'from-green-600 to-green-800'],
                [__('kpi_customers'),   $mtd['customers'],                          pctChange($mtd['customers'],  $lastMtd['customers']),  'people',         'from-purple-600 to-purple-800'],
                [__('kpi_avg_session'), '฿'.number_format($mtd['avg_rev'],0),      pctChange($mtd['avg_rev'],    $lastMtd['avg_rev']),    'trending_up',    'from-teal-600 to-teal-800'],
                [__('kpi_new_customers'),$newCustomers.' คน',                       '',                                                    'person_add',     'from-pink-600 to-pink-800'],
            ];
            foreach ($mKPIs as [$lbl,$val,$chg,$icon,$grad]):
            ?>
            <div class="glass-card rounded-2xl p-4">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center mb-3">
                    <span class="material-icons text-white text-lg"><?= $icon ?></span>
                </div>
                <p class="text-xs text-blue-300"><?= $lbl ?></p>
                <p class="text-xl font-bold text-white mt-0.5"><?= $val ?></p>
                <?php if ($chg): ?>
                <div class="mt-1 flex items-center gap-1">
                    <?= $chg ?><span class="text-blue-500 text-xs"><?= __('vs_last_month') ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── YTD KPIs -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="period_ytd" class="hidden">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <?php
            $yKPIs = [
                [__('kpi_revenue'),       '฿'.number_format($ytd['revenue'],2),    pctChange($ytd['revenue'],  $lastYtd['revenue']),   'payments',        'from-yellow-500 to-yellow-700'],
                [__('kpi_sessions'),      number_format($ytd['sessions']),          pctChange($ytd['sessions'], $lastYtd['sessions']),  'bolt',            'from-blue-600 to-blue-800'],
                [__('kpi_energy'),        number_format($ytd['kwh'],1).' kWh',     pctChange($ytd['kwh'],      $lastYtd['kwh']),       'electric_meter',  'from-green-600 to-green-800'],
                [__('kpi_total_customers'),$totalCustomers.' คน',                  '',                                                 'people',          'from-purple-600 to-purple-800'],
                [__('kpi_avg_session'),   '฿'.number_format($ytd['avg_rev'],0),    pctChange($ytd['avg_rev'],  $lastYtd['avg_rev']),   'trending_up',     'from-teal-600 to-teal-800'],
                [__('kpi_online_chargers'),$statusMap['Online'].'/'.$totalChargers,'',                                                 'ev_station',      'from-indigo-600 to-indigo-800'],
            ];
            foreach ($yKPIs as [$lbl,$val,$chg,$icon,$grad]):
            ?>
            <div class="glass-card rounded-2xl p-4">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center mb-3">
                    <span class="material-icons text-white text-lg"><?= $icon ?></span>
                </div>
                <p class="text-xs text-blue-300"><?= $lbl ?></p>
                <p class="text-xl font-bold text-white mt-0.5"><?= $val ?></p>
                <?php if ($chg): ?>
                <div class="mt-1 flex items-center gap-1">
                    <?= $chg ?><span class="text-blue-500 text-xs"><?= __('vs_last_year') ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── System Status Bar -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="glass-card rounded-2xl p-4 mb-6 flex flex-wrap gap-4 items-center">
        <p class="text-xs text-blue-300 font-semibold uppercase tracking-wide flex items-center gap-1">
            <span class="material-icons text-sm">electrical_services</span> <?= __('kpi_online_chargers') ?>
        </p>
        <?php
        $sbItems = [
            ['Online',   $statusMap['Online'],   'bg-green-500',  'text-green-300'],
            ['Offline',  $statusMap['Offline'],  'bg-gray-500',   'text-gray-300'],
            ['Faulted',  $statusMap['Faulted'],  'bg-red-500',    'text-red-300'],
            ['Updating', $statusMap['Updating'], 'bg-yellow-500', 'text-yellow-300'],
        ];
        foreach ($sbItems as [$label, $cnt, $bg, $txt]):
        ?>
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full <?= $bg ?>"></span>
            <span class="text-sm <?= $txt ?>"><?= $label ?></span>
            <span class="text-white font-bold text-sm"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
        <div class="ml-auto flex items-center gap-2 text-xs text-blue-300">
            <span class="material-icons text-sm text-yellow-400">bolt</span>
            <?= $activeNow ?> <?= __('kpi_active_now') ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── Charts Row 1: Daily + Monthly -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
        <!-- Daily Revenue 30d -->
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-bold text-white text-sm mb-4 flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-lg">show_chart</span>
                <?= __('chart_daily_revenue') ?>
            </h3>
            <canvas id="chartDaily" height="200"></canvas>
        </div>
        <!-- Monthly YTD -->
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-bold text-white text-sm mb-4 flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-lg">bar_chart</span>
                <?= __('chart_monthly') ?>
            </h3>
            <canvas id="chartMonthly" height="200"></canvas>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── Charts Row 2: Car Type + Station Pie + Hourly -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <!-- Car Type Donut -->
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-bold text-white text-sm mb-4 flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-lg">directions_car</span>
                <?= __('chart_car_type') ?>
            </h3>
            <canvas id="chartCarType" height="220"></canvas>
            <!-- Legend -->
            <div class="mt-3 space-y-1 max-h-28 overflow-y-auto">
                <?php foreach ($carTypes as $i => $ct): ?>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" id="ctColor<?= $i ?>"></span>
                        <span class="text-blue-200 truncate max-w-28"><?= h($ct['name']) ?></span>
                    </div>
                    <span class="text-white font-medium"><?= $ct['sessions'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Station Revenue Donut -->
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-bold text-white text-sm mb-4 flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-lg">ev_station</span>
                <?= __('chart_revenue_by_station') ?>
            </h3>
            <canvas id="chartStation" height="220"></canvas>
            <div class="mt-3 space-y-1">
                <?php foreach ($byStation as $bs): ?>
                <div class="flex items-center justify-between text-xs py-1 border-b border-blue-900/30">
                    <span class="text-blue-200 truncate max-w-32"><?= h($bs['name']) ?></span>
                    <span class="text-yellow-400 font-bold">฿<?= number_format($bs['rev'],0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Hourly Bar -->
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-bold text-white text-sm mb-4 flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-lg">access_time</span>
                <?= __('chart_hourly') ?>
            </h3>
            <canvas id="chartHourly" height="220"></canvas>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ── Bottom: Top Customers + Recent Sessions -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <!-- Top Customers -->
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-blue-800/50 flex items-center justify-between">
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <span class="material-icons text-yellow-400">emoji_events</span> <?= __('top_customers') ?>
                </h3>
                <a href="customers.php" class="text-xs text-blue-300 hover:text-yellow-400 transition"><?= __('all') ?> →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-blue-900/40 text-blue-400 uppercase">
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left"><?= __('cust_name') ?></th>
                            <th class="px-4 py-2 text-left"><?= __('cust_car_type') ?></th>
                            <th class="px-4 py-2 text-right"><?= __('kpi_sessions') ?></th>
                            <th class="px-4 py-2 text-right"><?= __('cust_spend') ?> (฿)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topCustomers as $i => $cu): ?>
                    <tr class="table-row border-b border-blue-900/20">
                        <td class="px-4 py-2.5">
                            <?php if ($i < 3): ?>
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                <?= ['bg-yellow-400 text-blue-900','bg-gray-300 text-blue-900','bg-amber-600 text-white'][$i] ?>">
                                <?= $i+1 ?>
                            </span>
                            <?php else: ?>
                            <span class="text-blue-400"><?= $i+1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="text-white font-medium"><?= h($cu['full_name']) ?></p>
                            <p class="text-blue-400"><?= h($cu['license_plate'] ?? '') ?></p>
                        </td>
                        <td class="px-4 py-2.5 text-blue-300"><?= h($cu['car_name'] ?? '-') ?></td>
                        <td class="px-4 py-2.5 text-right text-white font-bold"><?= $cu['sessions'] ?></td>
                        <td class="px-4 py-2.5 text-right text-yellow-400 font-bold"><?= number_format($cu['spend'],0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topCustomers)): ?>
                    <tr><td colspan="5" class="text-center py-6 text-blue-400"><?= __('no_data') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-blue-800/50 flex items-center justify-between">
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <span class="material-icons text-yellow-400">history</span> <?= __('recent_sessions') ?>
                </h3>
                <a href="transactions.php" class="text-xs text-blue-300 hover:text-yellow-400 transition"><?= __('all') ?> →</a>
            </div>
            <div class="divide-y divide-blue-900/30">
                <?php foreach ($recentSessions as $rs): ?>
                <div class="px-4 py-3 flex items-center gap-3 hover:bg-blue-800/10 transition">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br
                        <?= $rs['status']==='Charging' ? 'from-green-600 to-green-800' : 'from-blue-700 to-blue-900' ?>
                        flex items-center justify-center flex-shrink-0">
                        <span class="material-icons text-white text-base">
                            <?= $rs['status']==='Charging' ? 'bolt' : 'check_circle' ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-xs font-medium truncate">
                            <?= h($rs['cust_name'] ?? 'Unknown') ?>
                            <span class="text-blue-400 ml-1"><?= h($rs['license_plate'] ?? '') ?></span>
                        </p>
                        <p class="text-blue-400 text-xs truncate"><?= h($rs['station_name']) ?> · <?= h($rs['car_name'] ?? '-') ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-yellow-400 text-xs font-bold">฿<?= number_format($rs['actual_amount'],0) ?></p>
                        <p class="text-blue-400 text-xs"><?= number_format($rs['energy_kwh'],1) ?> kWh</p>
                    </div>
                    <div><?= transactionStatusBadge($rs['status']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recentSessions)): ?>
                <div class="py-8 text-center text-blue-400 text-sm"><?= __('no_data') ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Theme defaults
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgba(59,130,246,0.1)';
const palette = ['#f59e0b','#3b82f6','#10b981','#a855f7','#ef4444','#06b6d4','#f97316','#84cc16','#ec4899','#6366f1','#14b8a6','#8b5cf6'];

// ── Period Tabs
function showPeriod(p) {
    ['daily','mtd','ytd'].forEach(id => {
        document.getElementById('period_'+id).classList.toggle('hidden', id !== p);
        const tab = document.getElementById('tab_'+id);
        if (id === p) {
            tab.classList.add('bg-yellow-400','text-blue-900');
            tab.classList.remove('text-blue-300');
        } else {
            tab.classList.remove('bg-yellow-400','text-blue-900');
            tab.classList.add('text-blue-300');
        }
    });
}
showPeriod('daily');

// ── Daily Revenue Chart
new Chart(document.getElementById('chartDaily'), {
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [
            { type:'bar', label:'Revenue (฿)', data: <?= json_encode($chartRev) ?>,
              backgroundColor:'rgba(245,158,11,0.7)', borderRadius:4, yAxisID:'y' },
            { type:'line', label:'Sessions', data: <?= json_encode($chartSess) ?>,
              borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.1)',
              tension:0.4, pointRadius:2, yAxisID:'y1' }
        ]
    },
    options: { responsive:true, plugins:{legend:{labels:{color:'#94a3b8',font:{size:11}}}},
        scales: {
            x: { ticks:{color:'#64748b',font:{size:10}}, grid:{color:'rgba(59,130,246,0.07)'} },
            y: { position:'left', ticks:{color:'#94a3b8',callback:v=>'฿'+v.toLocaleString()}, grid:{color:'rgba(59,130,246,0.07)'} },
            y1:{ position:'right', ticks:{color:'#3b82f6'}, grid:{drawOnChartArea:false} }
        }
    }
});

// ── Monthly Chart
new Chart(document.getElementById('chartMonthly'), {
    data: {
        labels: <?= json_encode($monthNames) ?>,
        datasets: [
            { type:'bar', label:'Revenue (฿)', data: <?= json_encode($chartMonthRev) ?>,
              backgroundColor: <?= json_encode($monthNames) ?>.map((_,i)=> i===<?= (int)date('n')-1 ?> ? '#f59e0b':'rgba(59,130,246,0.6)'),
              borderRadius:6, yAxisID:'y' },
            { type:'line', label:'Sessions', data: <?= json_encode($chartMonthSess) ?>,
              borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.1)',
              tension:0.4, pointRadius:3, yAxisID:'y1' }
        ]
    },
    options: { responsive:true, plugins:{legend:{labels:{color:'#94a3b8',font:{size:11}}}},
        scales: {
            x: { ticks:{color:'#64748b'}, grid:{color:'rgba(59,130,246,0.07)'} },
            y: { position:'left', ticks:{color:'#94a3b8',callback:v=>'฿'+v.toLocaleString()}, grid:{color:'rgba(59,130,246,0.07)'} },
            y1:{ position:'right', ticks:{color:'#10b981'}, grid:{drawOnChartArea:false} }
        }
    }
});

// ── Car Type Donut
const ctLabels = <?= json_encode(array_column($carTypes,'name')) ?>;
const ctData   = <?= json_encode(array_column($carTypes,'sessions')) ?>;
const ctChart  = new Chart(document.getElementById('chartCarType'), {
    type:'doughnut',
    data: { labels: ctLabels, datasets:[{ data:ctData, backgroundColor:palette, borderWidth:2, borderColor:'#0f2040' }] },
    options: { responsive:true, cutout:'65%',
        plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.label}: ${ctx.raw} sessions`}} }
    }
});
// Color the legend dots
ctLabels.forEach((_,i)=>{
    const el = document.getElementById('ctColor'+i);
    if(el) el.style.background = palette[i % palette.length];
});

// ── Station Donut
const stLabels = <?= json_encode(array_column($byStation,'name')) ?>;
const stData   = <?= json_encode(array_column($byStation,'rev')) ?>;
new Chart(document.getElementById('chartStation'), {
    type:'doughnut',
    data: { labels:stLabels, datasets:[{ data:stData, backgroundColor:['#f59e0b','#3b82f6','#10b981','#a855f7'], borderWidth:2, borderColor:'#0f2040' }] },
    options: { responsive:true, cutout:'60%',
        plugins:{ legend:{position:'bottom', labels:{color:'#94a3b8',font:{size:10},padding:8}},
            tooltip:{callbacks:{label:ctx=>`฿${ctx.raw.toLocaleString()}`}} }
    }
});

// ── Hourly Chart
const hrLabels = Array.from({length:24},(_,i)=>i.toString().padStart(2,'0')+':00');
new Chart(document.getElementById('chartHourly'), {
    type:'bar',
    data: { labels:hrLabels,
        datasets:[{ label:'Sessions', data:<?= json_encode($chartHourSess) ?>,
            backgroundColor: hrLabels.map((_,i)=> (i>=9&&i<=22)?'rgba(245,158,11,0.8)':'rgba(59,130,246,0.5)'),
            borderRadius:3 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}},
        scales: {
            x:{ticks:{color:'#475569',font:{size:9},maxRotation:45}, grid:{display:false}},
            y:{ticks:{color:'#94a3b8',stepSize:1}, grid:{color:'rgba(59,130,246,0.07)'}}
        }
    }
});
</script>

<style>
.glass-card { background:rgba(15,32,64,0.7); backdrop-filter:blur(12px); border:1px solid rgba(59,130,246,0.2); }
.table-row:hover { background:rgba(59,130,246,0.06); }
.period-tab { color:#93c5fd; }
.period-tab.active { background:#f59e0b; color:#0a1628; }
</style>
<?php layoutFoot(); ?>
