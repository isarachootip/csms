<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId    = (int)$_SESSION['user_id'];
$stationId = (int)($_GET['station_id'] ?? 0);

$stations = DB::fetchAll("SELECT id,name FROM stations WHERE user_id=? ORDER BY name", [$userId]);
if (!$stationId && !empty($stations)) $stationId = (int)$stations[0]['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_fee') {
    $sid = (int)$_POST['station_id'];
    DB::execute("UPDATE service_fee_settings SET is_active=0 WHERE station_id=?", [$sid]);
    DB::insert(
        "INSERT INTO service_fee_settings (station_id,fee_type,price_per_kwh,price_per_minute,peak_price,offpeak_price,peak_start,peak_end,currency,effective_from,is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,1)",
        [$sid, $_POST['fee_type'], (float)$_POST['price_per_kwh'],
         (float)($_POST['price_per_minute'] ?? 0), (float)($_POST['peak_price'] ?? 0),
         (float)($_POST['offpeak_price'] ?? 0), $_POST['peak_start'] ?? '09:00:00',
         $_POST['peak_end'] ?? '22:00:00', 'THB', date('Y-m-d')]
    );
    flash('settings', __('flash_fee_saved'), 'success');
    header("Location: settings.php?station_id={$sid}"); exit;
}

$fee     = $stationId ? DB::fetchOne("SELECT * FROM service_fee_settings WHERE station_id=? AND is_active=1 ORDER BY id DESC LIMIT 1", [$stationId]) : null;
$history = $stationId ? DB::fetchAll("SELECT * FROM service_fee_settings WHERE station_id=? ORDER BY id DESC LIMIT 10", [$stationId]) : [];

layoutHead('settings_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('settings.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">
    <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
            <span class="material-icons text-yellow-400 text-3xl">tune</span>
            <?= __('settings_title') ?>
        </h2>
        <p class="text-blue-300 text-sm mt-1"><?= __('settings_subtitle') ?></p>
    </div>

    <?= flashAlert('settings') ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="glass-card rounded-2xl p-6">
                <!-- Station selector -->
                <div class="mb-5">
                    <label class="block text-xs text-blue-200 mb-1"><?= __('select_station') ?></label>
                    <select onchange="location='settings.php?station_id='+this.value"
                        class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                        <?php foreach ($stations as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= $stationId==$st['id']?'selected':'' ?>><?= h($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$stationId): ?>
                <div class="text-center py-10 text-blue-400">
                    <span class="material-icons text-5xl">ev_station</span>
                    <p class="mt-2"><?= __('no_station_msg') ?></p>
                </div>
                <?php else: ?>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_fee">
                    <input type="hidden" name="station_id" value="<?= $stationId ?>">

                    <!-- Fee Type -->
                    <div>
                        <label class="block text-sm font-semibold text-blue-200 mb-3">
                            <span class="material-icons text-base align-middle text-yellow-400">payments</span>
                            <?= __('fee_type_label') ?>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <?php
                            $feeTypes = [
                                ['kWh-Based',   'electric_meter',     __('fee_kwh'),  false],
                                ['Time-Based',  'timer',              __('fee_time'), true],
                                ['TOU',         'schedule',           __('fee_tou'),  true],
                                ['Free Charge', 'volunteer_activism', __('fee_free'), false],
                            ];
                            $curType = $fee['fee_type'] ?? 'kWh-Based';
                            foreach ($feeTypes as [$val, $icon, $label, $soon]):
                            ?>
                            <label class="cursor-pointer <?= $soon?'opacity-50 cursor-not-allowed':'' ?>">
                                <input type="radio" name="fee_type" value="<?= $val ?>"
                                    <?= $curType===$val?'checked':'' ?>
                                    <?= $soon?'disabled':'' ?>
                                    class="hidden peer" onchange="updateFeeForm()">
                                <div class="glass-card peer-checked:border-yellow-400 peer-checked:bg-yellow-500/10 rounded-xl p-4 flex items-center gap-3 transition-all border hover:border-blue-500/50">
                                    <span class="material-icons text-yellow-400 text-2xl"><?= $icon ?></span>
                                    <div>
                                        <p class="text-sm font-medium text-white"><?= $label ?></p>
                                        <?php if ($soon): ?><span class="text-xs text-blue-400"><?= __('coming_soon') ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- kWh settings -->
                    <div id="section_kwh" class="space-y-4">
                        <div class="border-t border-blue-800/50 pt-4">
                            <label class="block text-sm font-semibold text-blue-200 mb-3">
                                <span class="material-icons text-base align-middle text-yellow-400">price_change</span>
                                <?= __('price_per_kwh') ?>
                            </label>
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <input type="number" step="0.01" name="price_per_kwh" min="0"
                                        value="<?= h($fee['price_per_kwh'] ?? '4.00') ?>"
                                        class="input-field w-full rounded-xl px-4 py-2.5 text-lg font-bold"
                                        oninput="previewCalc()">
                                </div>
                                <span class="text-blue-300 text-sm"><?= __('per_kwh') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Free Charge info -->
                    <div id="section_free" class="hidden">
                        <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-4 flex items-center gap-3">
                            <span class="material-icons text-green-400 text-2xl">volunteer_activism</span>
                            <div>
                                <p class="text-green-300 font-semibold">Free Charge</p>
                                <p class="text-green-200 text-sm"><?= __('free_charge_desc') ?></p>
                            </div>
                        </div>
                        <input type="hidden" name="price_per_kwh" value="0">
                    </div>

                    <!-- Live Preview -->
                    <div id="livePreview" class="bg-blue-900/30 border border-blue-700/50 rounded-xl p-4">
                        <p class="text-xs text-blue-300 mb-3 font-semibold flex items-center gap-1">
                            <span class="material-icons text-sm">calculate</span> <?= __('calc_preview') ?>
                        </p>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <?php foreach ([100, 300, 500] as $amt): ?>
                            <div class="bg-blue-900/40 rounded-lg p-3">
                                <p class="text-yellow-400 font-bold text-sm"><?= $amt ?> ฿</p>
                                <p class="text-blue-300 text-xs" id="kwh_<?= $amt ?>">– kWh</p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold flex items-center justify-center gap-2">
                        <span class="material-icons">save</span> <?= __('save') ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="space-y-4">
            <div class="glass-card rounded-2xl p-5">
                <h3 class="font-bold text-white mb-4 flex items-center gap-2">
                    <span class="material-icons text-yellow-400">info</span> <?= __('fee_info_title') ?>
                </h3>
                <div class="space-y-3 text-sm">
                    <?php
                    $feeInfo = [
                        ['electric_meter',     'text-yellow-400',  'kWh-Based',   __('fee_kwh'),  __('fee_kwh_desc'),  false],
                        ['timer',              'text-blue-400',    'Time-Based',  __('fee_time'), __('fee_time_desc'), true],
                        ['schedule',           'text-purple-400',  'TOU',         __('fee_tou'),  __('fee_tou_desc'),  true],
                        ['volunteer_activism', 'text-green-400',   'Free Charge', __('fee_free'), __('fee_free_desc'), false],
                    ];
                    foreach ($feeInfo as [$icon, $cls, $key, $label, $desc, $soon]):
                    ?>
                    <div class="flex gap-3 items-start <?= $soon?'opacity-50':'' ?>">
                        <span class="material-icons <?= $cls ?> text-xl mt-0.5"><?= $icon ?></span>
                        <div>
                            <p class="text-white font-medium"><?= $label ?>
                                <?php if ($soon): ?><span class="text-xs bg-blue-800 text-blue-300 px-1.5 py-0.5 rounded-full ml-1"><?= __('coming_soon') ?></span><?php endif; ?>
                            </p>
                            <p class="text-blue-300 text-xs"><?= $desc ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($history)): ?>
            <div class="glass-card rounded-2xl p-5">
                <h3 class="font-bold text-white mb-3 flex items-center gap-2">
                    <span class="material-icons text-yellow-400">history</span> <?= __('fee_history') ?>
                </h3>
                <div class="space-y-2">
                    <?php foreach ($history as $hrow): ?>
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-blue-900/30">
                        <div>
                            <span class="text-white font-medium"><?= h($hrow['fee_type']) ?></span>
                            <?php if ($hrow['fee_type']==='kWh-Based'): ?>
                            <span class="text-yellow-400 ml-1"><?= number_format($hrow['price_per_kwh'],2) ?> <?= __('per_kwh') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($hrow['is_active']): ?>
                            <span class="bg-green-500/20 text-green-300 border border-green-500 px-1.5 py-0.5 rounded-full text-xs"><?= __('status_active') ?></span>
                            <?php endif; ?>
                            <span class="text-blue-400"><?= h($hrow['effective_from']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<script>
function updateFeeForm() {
    const type = document.querySelector('input[name="fee_type"]:checked')?.value || 'kWh-Based';
    document.getElementById('section_kwh').style.display  = type === 'kWh-Based'   ? '' : 'none';
    document.getElementById('section_free').style.display = type === 'Free Charge' ? '' : 'none';
    document.getElementById('livePreview').style.display  = type === 'kWh-Based'   ? '' : 'none';
}
function previewCalc() {
    const price = parseFloat(document.querySelector('input[name="price_per_kwh"]')?.value || 0);
    [100, 300, 500].forEach(amt => {
        const el = document.getElementById('kwh_' + amt);
        if (el) el.textContent = price > 0 ? (amt / price).toFixed(3) + ' kWh' : '–';
    });
}
updateFeeForm(); previewCalc();
</script>
<?php layoutFoot(); ?>
