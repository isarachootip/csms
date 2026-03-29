<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid    = capp_customer_id();
$txView = (int)($_GET['tx'] ?? 0);
$month  = $_GET['month'] ?? '';
$page   = max(1,(int)($_GET['page']??1));
$perPg  = 15;
$offset = ($page-1)*$perPg;

// Month filter
$where  = ["t.customer_id=?","t.status IN ('Completed','Stopped','Faulted')"];
$params = [$cid];
if ($month) { $where[] = "DATE_FORMAT(t.start_time,'%Y-%m')=?"; $params[] = $month; }

$whereStr = 'WHERE '.implode(' AND ',$where);
$total = (int)(DB::fetchOne("SELECT COUNT(*) AS n FROM transactions t $whereStr", $params)['n'] ?? 0);

$transactions = DB::fetchAll(
    "SELECT t.*, s.name AS station_name, ct.name AS car_name
     FROM transactions t
     JOIN stations s ON s.id=t.station_id
     LEFT JOIN car_types ct ON ct.id=t.car_type_id
     $whereStr ORDER BY t.start_time DESC LIMIT $perPg OFFSET $offset",
    $params
);

// Monthly summary
$monthSummary = DB::fetchAll(
    "SELECT DATE_FORMAT(start_time,'%Y-%m') AS month,
            COUNT(*) AS sessions, SUM(energy_kwh) AS kwh, SUM(actual_amount) AS spend
     FROM transactions WHERE customer_id=? AND status IN ('Completed','Stopped')
     GROUP BY DATE_FORMAT(start_time,'%Y-%m') ORDER BY month DESC LIMIT 12",
    [$cid]
);

// Total stats
$allStats = DB::fetchOne("SELECT COUNT(*) AS sessions, SUM(energy_kwh) AS kwh, SUM(actual_amount) AS spend
    FROM transactions WHERE customer_id=? AND status IN ('Completed','Stopped')", [$cid]);

// TX detail if requested
$txDetail = null;
if ($txView) {
    $txDetail = DB::fetchOne(
        "SELECT t.*, s.name AS station_name, s.address AS station_addr,
                c.brand AS charger_brand, c.model AS charger_model, c.max_power_kw,
                cn.connector_type, ct.name AS car_name
         FROM transactions t
         JOIN stations s ON s.id=t.station_id
         JOIN chargers c ON c.id=t.charger_id
         JOIN connectors cn ON cn.id=t.connector_id
         LEFT JOIN car_types ct ON ct.id=t.car_type_id
         WHERE t.id=? AND t.customer_id=?",
        [$txView, $cid]
    );
}

$pages = (int)ceil($total / $perPg);

capp_head('ประวัติการชาร์จ');
?>

<div class="page-content">
    <?php capp_top_bar('ประวัติการชาร์จ', false); ?>

    <div class="px-4 pt-3 space-y-4">

        <!-- ── All-time Stats ── -->
        <div class="glass rounded-2xl p-4 card-glow">
            <p class="text-xs text-gray-400 mb-2 font-semibold uppercase tracking-wide">สถิติทั้งหมด</p>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <p class="text-xl font-bold text-blue-300"><?= number_format((int)$allStats['sessions']) ?></p>
                    <p class="text-xs text-gray-500">ครั้ง</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-yellow-300"><?= fmt_kwh((float)$allStats['kwh']) ?></p>
                    <p class="text-xs text-gray-500">kWh</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-green-300">฿<?= number_format((float)$allStats['spend'],0) ?></p>
                    <p class="text-xs text-gray-500">บาท</p>
                </div>
            </div>
        </div>

        <!-- ── Month Filter ── -->
        <div>
            <p class="text-xs text-gray-400 font-semibold mb-2">กรองตามเดือน</p>
            <div class="flex gap-2 overflow-x-auto pb-1 -mx-4 px-4" style="scrollbar-width:none">
                <a href="history.php"
                   class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                          <?= !$month ? 'bg-blue-600 border-blue-600 text-white' : 'border-blue-700/50 text-blue-300' ?>">
                    ทั้งหมด
                </a>
                <?php foreach ($monthSummary as $ms): ?>
                <?php
                    $d = new DateTime($ms['month'].'-01');
                    $label = $d->format('M Y');
                ?>
                <a href="history.php?month=<?= $ms['month'] ?>"
                   class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                          <?= $month===$ms['month'] ? 'bg-blue-600 border-blue-600 text-white' : 'border-blue-700/50 text-blue-300' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Transaction List ── -->
        <?php if (!empty($month)): ?>
        <?php
        $mStats = DB::fetchOne("SELECT COUNT(*) AS s, SUM(energy_kwh) AS k, SUM(actual_amount) AS sp
            FROM transactions WHERE customer_id=? AND status IN ('Completed','Stopped')
            AND DATE_FORMAT(start_time,'%Y-%m')=?", [$cid,$month]);
        ?>
        <div class="flex gap-3">
            <?php foreach ([['ครั้ง',$mStats['s'],'text-blue-300'],['kWh',fmt_kwh((float)$mStats['k']),'text-yellow-300'],['฿',number_format((float)$mStats['sp'],0),'text-green-300']] as [$l,$v,$c]): ?>
            <div class="flex-1 glass rounded-xl p-2 text-center">
                <p class="text-sm font-bold <?= $c ?>"><?= $v ?></p>
                <p class="text-xs text-gray-500"><?= $l ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($transactions)): ?>
        <div class="glass rounded-2xl py-16 text-center">
            <span class="material-icons-round text-5xl text-gray-600 block mb-3">receipt_long</span>
            <p class="text-gray-400">ยังไม่มีประวัติการชาร์จ</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($transactions as $tx): ?>
            <?php $ok = $tx['status']==='Completed'; ?>
            <button onclick="showDetail(<?= $tx['id'] ?>)" class="w-full text-left">
                <div class="glass rounded-xl px-4 py-3 flex items-center gap-3 hover:border-blue-500/40 transition">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                                <?= $ok ? 'bg-green-900/40' : 'bg-gray-800' ?>">
                        <span class="material-icons-round text-base <?= $ok ? 'text-green-400' : 'text-gray-400' ?>">bolt</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($tx['station_name']) ?></p>
                        <p class="text-xs text-gray-400">
                            <?= date('d/m/Y H:i', strtotime($tx['start_time'])) ?>
                            · <?= fmt_kwh((float)$tx['energy_kwh']) ?> kWh
                        </p>
                        <?php if ($tx['car_name']): ?>
                        <p class="text-xs text-blue-300">🚗 <?= htmlspecialchars($tx['car_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-white">฿<?= fmt_thb((float)$tx['actual_amount']) ?></p>
                        <p class="text-xs <?= $ok?'text-green-400':'text-gray-400' ?>"><?= $ok?'สำเร็จ':'หยุด' ?></p>
                        <p class="text-xs text-gray-500"><?= fmt_dur($tx['duration_minutes']) ?></p>
                    </div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex justify-center gap-2 pb-4">
            <?php for ($p=1;$p<=$pages;$p++): ?>
            <a href="?page=<?=$p?><?= $month?'&month='.$month:'' ?>"
               class="w-9 h-9 rounded-full flex items-center justify-center text-sm border transition
                      <?= $p===$page ? 'bg-blue-600 border-blue-600 text-white' : 'border-blue-800 text-blue-300' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── TX Detail Modal ── -->
<div id="txModal" class="fixed inset-0 z-50 hidden flex items-end justify-center p-0"
     style="background:rgba(0,0,0,.6);backdrop-filter:blur(8px)">
    <div class="glass rounded-t-2xl w-full max-w-lg p-5 pb-8 slide-up" style="max-height:85vh;overflow-y:auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-white">รายละเอียดการชาร์จ</h3>
            <button onclick="closeModal()" class="text-gray-400"><span class="material-icons-round">close</span></button>
        </div>
        <div id="txModalContent"></div>
    </div>
</div>

<script>
const txData = <?= json_encode(array_column($transactions, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

function showDetail(id) {
    const t = txData[id];
    if (!t) return;
    const ok = t.status === 'Completed';
    const html = `
        <div class="text-center mb-4">
            <span class="material-icons-round text-4xl ${ok?'text-green-400':'text-gray-400'}">${ok?'check_circle':'cancel'}</span>
            <p class="font-bold text-white mt-1">${t.station_name}</p>
            <p class="text-xs text-gray-400">${t.start_time}</p>
        </div>
        <div class="space-y-3 mb-4">
            <div class="grid grid-cols-2 gap-3 text-center">
                <div class="bg-blue-900/40 rounded-xl p-3"><p class="text-xs text-gray-400">⏱ เวลา</p><p class="font-bold text-blue-300">${t.duration_minutes} นาที</p></div>
                <div class="bg-yellow-900/30 rounded-xl p-3"><p class="text-xs text-gray-400">⚡ พลังงาน</p><p class="font-bold text-yellow-300">${parseFloat(t.energy_kwh).toFixed(2)} kWh</p></div>
                <div class="bg-green-900/30 rounded-xl p-3"><p class="text-xs text-gray-400">💰 ค่าบริการ</p><p class="font-bold text-green-300">฿${parseFloat(t.actual_amount).toFixed(2)}</p></div>
                <div class="bg-purple-900/30 rounded-xl p-3"><p class="text-xs text-gray-400">🌱 CO₂ ลด</p><p class="font-bold text-purple-300">${(parseFloat(t.energy_kwh)*0.5).toFixed(2)} kg</p></div>
            </div>
            <div class="glass rounded-xl p-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-gray-400">ราคา/kWh</span><span class="text-white">฿${parseFloat(t.price_per_kwh).toFixed(2)}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">เริ่ม</span><span class="text-white">${t.start_time}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">สิ้นสุด</span><span class="text-white">${t.stop_time||'-'}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">สถานะ</span><span class="${ok?'text-green-400':'text-gray-400'}">${ok?'สำเร็จ':'หยุด'}</span></div>
            </div>
        </div>
        <a href="session.php?id=${id}" class="btn-blue w-full py-3 rounded-xl text-sm text-center block">ดูหน้าสรุปเต็ม</a>
    `;
    document.getElementById('txModalContent').innerHTML = html;
    document.getElementById('txModal').classList.remove('hidden');
}
function closeModal() { document.getElementById('txModal').classList.add('hidden'); }
document.getElementById('txModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });

// Auto-open if tx param set
<?php if ($txView && $txDetail): ?>
showDetail(<?= $txView ?>);
<?php endif; ?>
</script>

<?php capp_bottom_nav('history'); ?>
<?php capp_foot(); ?>
