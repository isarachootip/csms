<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId    = (int)$_SESSION['user_id'];
$stationId = (int)($_GET['station_id'] ?? 0);
if (!$stationId) { header('Location: stations.php'); exit; }

$station = DB::fetchOne("SELECT * FROM stations WHERE id=? AND user_id=?", [$stationId, $userId]);
if (!$station) { header('Location: stations.php'); exit; }

$redir = "chargers.php?station_id={$stationId}";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_charger') {
        $cid = DB::insert(
            "INSERT INTO chargers (station_id,serial_number,model,brand,max_power_kw,controller_status) VALUES (?,?,?,?,?,?)",
            [$stationId, trim($_POST['serial_number']), trim($_POST['model']), trim($_POST['brand']),
             (float)($_POST['max_power_kw'] ?? 0), 'Offline']
        );
        DB::insert("INSERT INTO connectors (charger_id,connector_number,connector_type,status) VALUES (?,?,?,?)",
            [$cid, 1, $_POST['connector_type'] ?? 'Type2', 'Unavailable']);
        flash('charger', __('flash_charger_added'), 'success');
        header("Location: {$redir}"); exit;
    }

    if ($action === 'edit_charger') {
        DB::execute(
            "UPDATE chargers SET serial_number=?,model=?,brand=?,max_power_kw=? WHERE id=? AND station_id=?",
            [trim($_POST['serial_number']), trim($_POST['model']), trim($_POST['brand']),
             (float)$_POST['max_power_kw'], (int)$_POST['id'], $stationId]
        );
        flash('charger', __('flash_charger_updated'), 'success');
        header("Location: {$redir}"); exit;
    }

    if ($action === 'delete_charger') {
        DB::execute("DELETE FROM chargers WHERE id=? AND station_id=?", [(int)$_POST['id'], $stationId]);
        flash('charger', __('flash_charger_deleted'), 'success');
        header("Location: {$redir}"); exit;
    }

    if ($action === 'start_charge') {
        $connectorId = (int)$_POST['connector_id'];
        $chargerId   = (int)$_POST['charger_id'];
        $estAmt      = (float)$_POST['estimate_amount'];
        $remark      = trim($_POST['remark'] ?? '');
        $fee         = DB::fetchOne("SELECT * FROM service_fee_settings WHERE station_id=? AND is_active=1 ORDER BY id DESC LIMIT 1", [$stationId]);
        $price       = $fee ? (float)$fee['price_per_kwh'] : 4.00;
        $feeType     = $fee ? $fee['fee_type'] : 'kWh-Based';
        $kwh         = ($price > 0 && $feeType === 'kWh-Based') ? round($estAmt / $price, 4) : 0;
        DB::insert(
            "INSERT INTO transactions (connector_id,charger_id,station_id,user_id,estimate_amount,energy_kwh,start_time,status,remark,fee_type,price_per_kwh)
             VALUES (?,?,?,?,?,?,NOW(),'Charging',?,?,?)",
            [$connectorId, $chargerId, $stationId, $userId, $estAmt, $kwh, $remark, $feeType, $price]
        );
        DB::execute("UPDATE connectors SET status='Charging in progress' WHERE id=?", [$connectorId]);
        flash('charger', __('flash_start_ok'), 'success');
        header("Location: {$redir}"); exit;
    }

    if ($action === 'stop_charge') {
        $txId        = (int)$_POST['transaction_id'];
        $connectorId = (int)$_POST['connector_id'];
        $kwhUsed     = (float)($_POST['energy_used'] ?? 0);
        $tx          = DB::fetchOne("SELECT * FROM transactions WHERE id=?", [$txId]);
        if ($tx) {
            $actual = $kwhUsed * (float)$tx['price_per_kwh'];
            DB::execute(
                "UPDATE transactions SET status='Stopped',stop_time=NOW(),energy_kwh=?,actual_amount=?,
                 duration_minutes=TIMESTAMPDIFF(MINUTE,start_time,NOW()) WHERE id=?",
                [$kwhUsed, $actual, $txId]
            );
        }
        DB::execute("UPDATE connectors SET status='Charging finish' WHERE id=?", [$connectorId]);
        flash('charger', __('flash_stop_ok'), 'success');
        header("Location: {$redir}"); exit;
    }

    if ($action === 'sim_status') {
        DB::execute("UPDATE chargers SET controller_status=?,last_heartbeat=NOW() WHERE id=?",
            [$_POST['controller_status'], (int)$_POST['id']]);
        flash('charger', __('flash_status_updated'), 'success');
        header("Location: {$redir}"); exit;
    }
}

$chargers = DB::fetchAll(
    "SELECT c.*,
        (SELECT status FROM connectors WHERE charger_id=c.id ORDER BY connector_number LIMIT 1) AS connector_status,
        (SELECT id FROM connectors WHERE charger_id=c.id ORDER BY connector_number LIMIT 1) AS connector_id,
        (SELECT id FROM transactions WHERE charger_id=c.id AND status='Charging' LIMIT 1) AS active_tx_id
     FROM chargers c WHERE c.station_id=? ORDER BY c.id",
    [$stationId]
);
$fee         = DB::fetchOne("SELECT * FROM service_fee_settings WHERE station_id=? AND is_active=1 ORDER BY id DESC LIMIT 1", [$stationId]);
$pricePerKwh = $fee ? (float)$fee['price_per_kwh'] : 4.00;

layoutHead('chargers_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('chargers.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-blue-300 mb-4">
        <a href="stations.php" class="hover:text-yellow-400 flex items-center gap-1 transition">
            <span class="material-icons text-base">ev_station</span> <?= __('nav_stations') ?>
        </a>
        <span class="material-icons text-base">chevron_right</span>
        <span class="text-white font-medium"><?= h($station['name']) ?></span>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-3xl">electrical_services</span>
                <?= __('chargers_title') ?>
            </h2>
            <p class="text-blue-300 text-sm mt-1"><?= h($station['name']) ?> · <?= count($chargers) ?> <?= __('charger_count') ?></p>
        </div>
        <button onclick="document.getElementById('modalAddCharger').classList.remove('hidden')"
            class="flex items-center gap-2 bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold px-5 py-2.5 rounded-xl shadow-lg hover:shadow-yellow-500/30 transition">
            <span class="material-icons">add_circle</span> <?= __('add_charger') ?>
        </button>
    </div>

    <?= flashAlert('charger') ?>

    <!-- Price Banner -->
    <?php if ($fee): ?>
    <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/30 text-yellow-300 rounded-xl px-4 py-2.5 mb-5 text-sm">
        <span class="material-icons text-base">price_change</span>
        <?= __('price_banner') ?>: <strong><?= number_format($pricePerKwh, 2) ?> <?= __('per_kwh') ?></strong>
        (<?= h($fee['fee_type']) ?>)
        <a href="settings.php?station_id=<?= $stationId ?>" class="ml-auto text-xs underline hover:text-yellow-200 flex items-center gap-0.5">
            <span class="material-icons text-sm">tune</span> <?= __('nav_settings') ?>
        </a>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-2 bg-orange-500/10 border border-orange-500/30 text-orange-300 rounded-xl px-4 py-2.5 mb-5 text-sm">
        <span class="material-icons text-base">warning</span>
        <?= __('no_fee_warning') ?>
        <a href="settings.php?station_id=<?= $stationId ?>" class="ml-auto text-xs underline hover:text-orange-200"><?= __('set_fee_now') ?></a>
    </div>
    <?php endif; ?>

    <!-- Charger Cards -->
    <?php if (empty($chargers)): ?>
    <div class="glass-card rounded-2xl p-12 text-center">
        <span class="material-icons text-blue-700 text-6xl">electrical_services</span>
        <p class="text-blue-300 mt-3"><?= __('no_charger') ?></p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($chargers as $ch):
            $isOnline    = $ch['controller_status'] === 'Online';
            $isCharging  = $ch['connector_status']  === 'Charging in progress';
            $isPluggedIn = $ch['connector_status']  === 'Plugged in';
            $canStart    = $isOnline && $isPluggedIn && !$isCharging;
            $canStop     = $isCharging && $ch['active_tx_id'];
        ?>
        <div class="glass-card rounded-2xl overflow-hidden <?= $isCharging ? 'border-green-500/40' : '' ?>">
            <div class="p-4 border-b border-blue-800/50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= $isOnline ? 'from-green-600 to-green-800' : 'from-gray-600 to-gray-800' ?> flex items-center justify-center">
                        <span class="material-icons text-white text-xl"><?= $isCharging ? 'bolt' : 'electrical_services' ?></span>
                    </div>
                    <div>
                        <p class="font-bold text-white text-sm"><?= h($ch['brand'] ? $ch['brand'].' '.$ch['model'] : 'Charger #'.$ch['id']) ?></p>
                        <p class="text-xs text-blue-300">S/N: <?= h($ch['serial_number']) ?></p>
                    </div>
                </div>
                <?= controllerStatusBadge($ch['controller_status']) ?>
            </div>

            <div class="p-4 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-blue-300 flex items-center gap-1"><span class="material-icons text-base">speed</span> <?= __('max_power') ?></span>
                    <span class="text-white font-medium"><?= h($ch['max_power_kw']) ?> kW</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-blue-300 flex items-center gap-1"><span class="material-icons text-base">power</span> <?= __('connector_type') ?></span>
                    <span><?= connectorStatusBadge($ch['connector_status'] ?? 'Unavailable') ?></span>
                </div>
                <?php if ($ch['last_heartbeat']): ?>
                <div class="flex justify-between text-xs">
                    <span class="text-blue-400"><span class="material-icons text-sm align-middle">schedule</span> <?= __('heartbeat') ?></span>
                    <span class="text-blue-300"><?= formatDateTH($ch['last_heartbeat']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($isCharging): ?>
                <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-3 flex items-center gap-2 charging-animation">
                    <span class="material-icons text-green-400 text-xl">bolt</span>
                    <div>
                        <p class="text-green-300 text-xs font-bold"><?= __('charging_active') ?></p>
                        <p class="text-green-200 text-xs">Transaction #<?= $ch['active_tx_id'] ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="px-4 pb-4 flex flex-col gap-2">
                <?php if ($canStart): ?>
                <button onclick="openStartModal(<?= $ch['id'] ?>,<?= $ch['connector_id'] ?>,<?= $pricePerKwh ?>)"
                    class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-700 text-white font-bold py-2.5 rounded-xl text-sm hover:shadow-lg hover:shadow-green-500/30 transition">
                    <span class="material-icons">play_circle</span> <?= __('start_charging') ?>
                </button>
                <?php elseif ($canStop): ?>
                <button onclick="openStopModal(<?= $ch['active_tx_id'] ?>,<?= $ch['connector_id'] ?>)"
                    class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-red-500 to-red-700 text-white font-bold py-2.5 rounded-xl text-sm hover:shadow-lg transition">
                    <span class="material-icons">stop_circle</span> <?= __('stop_charging') ?>
                </button>
                <?php else: ?>
                <div class="w-full text-center py-2.5 text-xs text-blue-400 border border-blue-800/50 rounded-xl">
                    <?php if (!$isOnline): ?>
                    <span class="material-icons text-sm align-middle">wifi_off</span> <?= __('offline_msg') ?>
                    <?php elseif ($ch['connector_status'] === 'Charging finish'): ?>
                    <span class="material-icons text-sm align-middle">check_circle</span> <?= __('wait_unplug') ?>
                    <?php else: ?>
                    <span class="material-icons text-sm align-middle">block</span> <?= __('cannot_charge') ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="flex gap-2">
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="action" value="sim_status">
                        <input type="hidden" name="id" value="<?= $ch['id'] ?>">
                        <select name="controller_status" onchange="this.form.submit()"
                            class="w-full bg-blue-900/50 border border-blue-700 text-blue-300 text-xs rounded-lg px-2 py-1.5">
                            <option value=""><?= __('simulate_status') ?></option>
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                            <option value="Faulted">Faulted</option>
                            <option value="Updating">Updating</option>
                        </select>
                    </form>
                    <button onclick="openEditCharger(<?= htmlspecialchars(json_encode($ch), ENT_QUOTES) ?>)"
                        class="px-3 py-1.5 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 transition">
                        <span class="material-icons text-base">edit</span>
                    </button>
                    <button onclick="deleteCharger(<?= $ch['id'] ?>)"
                        class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 transition">
                        <span class="material-icons text-base">delete</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</div>

<!-- Add Charger Modal -->
<div id="modalAddCharger" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-yellow-400">add_box</span> <?= __('add_charger_title') ?></h3>
            <button onclick="document.getElementById('modalAddCharger').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_charger">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('serial_number') ?> *</label>
                <input type="text" name="serial_number" required placeholder="EVCS-001" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('brand') ?></label>
                    <input type="text" name="brand" placeholder="ABB, Schneider..." class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('model') ?></label>
                    <input type="text" name="model" placeholder="Terra DC" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('max_power') ?></label>
                    <input type="number" step="0.1" name="max_power_kw" placeholder="7.4" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('connector_type') ?></label>
                    <select name="connector_type" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                        <option value="Type2">Type 2</option>
                        <option value="CCS2">CCS2</option>
                        <option value="CHAdeMO">CHAdeMO</option>
                        <option value="CCS1">CCS1</option>
                        <option value="GB/T">GB/T</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalAddCharger').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">save</span> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Charger Modal -->
<div id="modalEditCharger" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-yellow-400">edit</span> <?= __('edit_charger_title') ?></h3>
            <button onclick="document.getElementById('modalEditCharger').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_charger">
            <input type="hidden" name="id" id="editChargerId">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('serial_number') ?> *</label>
                <input type="text" name="serial_number" id="editSerialNo" required class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('brand') ?></label>
                    <input type="text" name="brand" id="editBrand" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1"><?= __('model') ?></label>
                    <input type="text" name="model" id="editModel" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('max_power') ?></label>
                <input type="number" step="0.1" name="max_power_kw" id="editMaxPower" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalEditCharger').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">save</span> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Start Charging Modal -->
<div id="modalStart" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-green-400">play_circle</span> <?= __('start_charge_title') ?></h3>
            <button onclick="document.getElementById('modalStart').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="start_charge">
            <input type="hidden" name="charger_id" id="startChargerId">
            <input type="hidden" name="connector_id" id="startConnectorId">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><span class="material-icons text-sm align-middle">payments</span> <?= __('estimate_amount') ?> *</label>
                <input type="number" step="0.01" name="estimate_amount" id="estimateAmount" required min="1"
                    oninput="calcKwh(this.value)" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div id="chargeSummary" class="hidden bg-blue-900/40 border border-blue-700/50 rounded-xl p-4 space-y-2 text-sm">
                <p class="text-blue-200 font-semibold flex items-center gap-1"><span class="material-icons text-base">summarize</span> <?= __('charge_summary') ?></p>
                <div class="flex justify-between">
                    <span class="text-blue-300"><?= __('price_per_unit') ?></span>
                    <span class="text-yellow-400 font-bold" id="summaryPrice">-</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-blue-300"><?= __('energy_estimate') ?></span>
                    <span class="text-green-400 font-bold" id="summaryKwh">-</span>
                </div>
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><span class="material-icons text-sm align-middle">note</span> <?= __('remark') ?></label>
                <input type="text" name="remark" placeholder="<?= __('remark_placeholder') ?>" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="document.getElementById('modalStart').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-green-500 to-green-700 text-white font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">bolt</span> <?= __('start_charge_btn') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stop Charging Modal -->
<div id="modalStop" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2"><span class="material-icons text-red-400">stop_circle</span> <?= __('stop_charge_title') ?></h3>
            <button onclick="document.getElementById('modalStop').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-3 mb-4 text-sm text-red-300 flex items-center gap-2">
            <span class="material-icons">warning</span> <?= __('stop_confirm_msg') ?>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="stop_charge">
            <input type="hidden" name="transaction_id" id="stopTxId">
            <input type="hidden" name="connector_id" id="stopConnectorId">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('energy_used') ?></label>
                <input type="number" step="0.001" name="energy_used" placeholder="0.000" min="0"
                    class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modalStop').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-700 text-white font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">stop_circle</span> <?= __('stop_charging') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<form id="deleteChargerForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_charger">
    <input type="hidden" name="id" id="deleteChargerId">
</form>

<script>
let currentPrice = 4;
const deleteMsg = <?= json_encode(__('delete_charger_confirm')) ?>;
function openStartModal(cid, conid, price) {
    currentPrice = price;
    document.getElementById('startChargerId').value = cid;
    document.getElementById('startConnectorId').value = conid;
    document.getElementById('estimateAmount').value = '';
    document.getElementById('chargeSummary').classList.add('hidden');
    document.getElementById('modalStart').classList.remove('hidden');
}
function calcKwh(amount) {
    const a = parseFloat(amount);
    if (!isNaN(a) && a > 0 && currentPrice > 0) {
        document.getElementById('summaryPrice').textContent = currentPrice.toFixed(2) + ' <?= __('per_kwh') ?>';
        document.getElementById('summaryKwh').textContent = (a / currentPrice).toFixed(3) + ' kWh';
        document.getElementById('chargeSummary').classList.remove('hidden');
    } else {
        document.getElementById('chargeSummary').classList.add('hidden');
    }
}
function openStopModal(txId, conId) {
    document.getElementById('stopTxId').value = txId;
    document.getElementById('stopConnectorId').value = conId;
    document.getElementById('modalStop').classList.remove('hidden');
}
function openEditCharger(ch) {
    document.getElementById('editChargerId').value = ch.id;
    document.getElementById('editSerialNo').value = ch.serial_number;
    document.getElementById('editBrand').value = ch.brand || '';
    document.getElementById('editModel').value = ch.model || '';
    document.getElementById('editMaxPower').value = ch.max_power_kw;
    document.getElementById('modalEditCharger').classList.remove('hidden');
}
function deleteCharger(id) {
    if (confirm(deleteMsg)) {
        document.getElementById('deleteChargerId').value = id;
        document.getElementById('deleteChargerForm').submit();
    }
}
</script>
<?php layoutFoot(); ?>
