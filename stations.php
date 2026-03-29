<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

Auth::requireLogin();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        DB::insert(
            "INSERT INTO stations (user_id,name,location,address,latitude,longitude,status) VALUES (?,?,?,?,?,?,?)",
            [$userId, trim($_POST['name']), trim($_POST['location']), trim($_POST['address']),
             $_POST['latitude'] ?: null, $_POST['longitude'] ?: null, 'active']
        );
        flash('station', __('flash_station_created'), 'success');
        header('Location: stations.php'); exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        DB::execute(
            "UPDATE stations SET name=?,location=?,address=?,latitude=?,longitude=?,status=? WHERE id=? AND user_id=?",
            [trim($_POST['name']), trim($_POST['location']), trim($_POST['address']),
             $_POST['latitude'] ?: null, $_POST['longitude'] ?: null, $_POST['status'], $id, $userId]
        );
        flash('station', __('flash_station_updated'), 'success');
        header('Location: stations.php'); exit;
    }

    if ($action === 'delete') {
        DB::execute("DELETE FROM stations WHERE id=? AND user_id=?", [(int)$_POST['id'], $userId]);
        flash('station', __('flash_station_deleted'), 'success');
        header('Location: stations.php'); exit;
    }
}

$stations = DB::fetchAll(
    "SELECT s.*,
        (SELECT COUNT(*) FROM chargers WHERE station_id=s.id) AS charger_count,
        (SELECT COUNT(*) FROM chargers WHERE station_id=s.id AND controller_status='Online') AS online_count,
        (SELECT COUNT(*) FROM transactions WHERE station_id=s.id AND status='Charging') AS active_sessions
     FROM stations s WHERE s.user_id=? ORDER BY s.created_at DESC",
    [$userId]
);

layoutHead('stations_title');
?>
<div class="flex min-h-screen">
<?php layoutNav('stations.php'); ?>

<main class="flex-1 p-4 md:p-6 pt-16 md:pt-6 overflow-x-hidden">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400 text-3xl">ev_station</span>
                <?= __('stations_title') ?>
            </h2>
            <p class="text-blue-300 text-sm mt-1"><?= __('stations_total', count($stations)) ?></p>
        </div>
        <button onclick="document.getElementById('modalCreate').classList.remove('hidden')"
            class="flex items-center gap-2 bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold px-5 py-2.5 rounded-xl shadow-lg hover:shadow-yellow-500/30 transition">
            <span class="material-icons">add_circle</span> <?= __('add_station') ?>
        </button>
    </div>

    <?= flashAlert('station') ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $totalChargers  = array_sum(array_column($stations, 'charger_count'));
        $totalOnline    = array_sum(array_column($stations, 'online_count'));
        $totalSessions  = array_sum(array_column($stations, 'active_sessions'));
        $activeStations = count(array_filter($stations, fn($s) => $s['status'] === 'active'));
        $cards = [
            [__('station_all'),     count($stations),    'ev_station',          'from-blue-600 to-blue-800'],
            [__('station_active'),  $activeStations,     'check_circle',        'from-green-600 to-green-800'],
            [__('charger_count'),   $totalChargers,      'electrical_services', 'from-yellow-500 to-yellow-700'],
            [__('active_sessions'), $totalSessions,      'bolt',                'from-purple-600 to-purple-800'],
        ];
        foreach ($cards as [$label, $value, $icon, $grad]):
        ?>
        <div class="glass-card rounded-2xl p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br <?= $grad ?> flex items-center justify-center flex-shrink-0">
                <span class="material-icons text-white text-xl"><?= $icon ?></span>
            </div>
            <div>
                <p class="text-xs text-blue-300"><?= $label ?></p>
                <p class="text-2xl font-bold text-white"><?= $value ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Stations Grid -->
    <?php if (empty($stations)): ?>
    <div class="glass-card rounded-2xl p-12 text-center">
        <span class="material-icons text-blue-700 text-6xl">ev_station</span>
        <p class="text-blue-300 mt-3"><?= __('no_station') ?></p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($stations as $st): ?>
        <div class="glass-card rounded-2xl p-5 flex flex-col gap-3 hover:border-yellow-500/40 transition-all duration-200">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-700 to-blue-900 flex items-center justify-center flex-shrink-0">
                        <span class="material-icons text-yellow-400 text-2xl">ev_station</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm leading-tight"><?= h($st['name']) ?></h3>
                        <p class="text-xs text-blue-300 mt-0.5"><?= h($st['location'] ?: '-') ?></p>
                    </div>
                </div>
                <?php
                $stLabel = match($st['status']) {
                    'active'      => __('status_active'),
                    'inactive'    => __('status_inactive'),
                    'maintenance' => __('status_maintenance'),
                    default       => $st['status'],
                };
                $stCls = $st['status']==='active'
                    ? 'bg-green-500/20 text-green-300 border-green-500'
                    : 'bg-gray-500/20 text-gray-400 border-gray-500';
                $stIcon = $st['status']==='active' ? 'check_circle' : 'cancel';
                ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium <?= $stCls ?>">
                    <span class="material-icons text-sm"><?= $stIcon ?></span><?= $stLabel ?>
                </span>
            </div>

            <p class="text-xs text-blue-400 flex items-start gap-1">
                <span class="material-icons text-sm">location_on</span>
                <?= h($st['address'] ?: __('no_address')) ?>
            </p>

            <div class="flex items-center gap-4 text-xs">
                <span class="flex items-center gap-1 text-blue-300">
                    <span class="material-icons text-sm">electrical_services</span>
                    <?= $st['charger_count'] ?> <?= __('charger_count') ?>
                </span>
                <span class="flex items-center gap-1 text-green-300">
                    <span class="material-icons text-sm">wifi</span>
                    <?= $st['online_count'] ?> <?= __('online_count') ?>
                </span>
                <?php if ($st['active_sessions'] > 0): ?>
                <span class="flex items-center gap-1 text-yellow-300 charging-animation">
                    <span class="material-icons text-sm">bolt</span>
                    <?= $st['active_sessions'] ?> <?= __('charging_now') ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-blue-800/50">
                <a href="chargers.php?station_id=<?= $st['id'] ?>"
                    class="flex-1 flex items-center justify-center gap-1 bg-blue-700/50 hover:bg-blue-600/60 text-blue-200 hover:text-white text-xs font-medium py-2 rounded-xl transition">
                    <span class="material-icons text-base">electrical_services</span> <?= __('manage_chargers') ?>
                </a>
                <button onclick="openEdit(<?= htmlspecialchars(json_encode($st), ENT_QUOTES) ?>)"
                    class="p-2 rounded-xl bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 transition">
                    <span class="material-icons text-lg">edit</span>
                </button>
                <button onclick="confirmDelete(<?= $st['id'] ?>, <?= htmlspecialchars(json_encode($st['name']), ENT_QUOTES) ?>)"
                    class="p-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 transition">
                    <span class="material-icons text-lg">delete</span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</div>

<!-- Modal: Create -->
<div id="modalCreate" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400">add_location</span> <?= __('add_station_title') ?>
            </h3>
            <button onclick="document.getElementById('modalCreate').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_name') ?> *</label>
                <input type="text" name="name" required class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_location') ?></label>
                <input type="text" name="location" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_address') ?></label>
                <textarea name="address" rows="2" class="input-field w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-blue-200 mb-1">Latitude</label>
                    <input type="number" step="any" name="latitude" placeholder="13.7563" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1">Longitude</label>
                    <input type="number" step="any" name="longitude" placeholder="100.5018" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalCreate').classList.add('hidden')"
                    class="flex-1 py-2.5 rounded-xl border border-blue-700 text-blue-300 text-sm transition"><?= __('cancel') ?></button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-yellow-400 to-yellow-600 text-blue-900 font-bold text-sm flex items-center justify-center gap-1">
                    <span class="material-icons text-base">save</span> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-white flex items-center gap-2">
                <span class="material-icons text-yellow-400">edit_location</span> <?= __('edit_station') ?>
            </h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-blue-400 hover:text-white"><span class="material-icons">close</span></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_name') ?> *</label>
                <input type="text" name="name" id="editName" required class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_location') ?></label>
                <input type="text" name="location" id="editLocation" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('station_address') ?></label>
                <textarea name="address" id="editAddress" rows="2" class="input-field w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-blue-200 mb-1">Latitude</label>
                    <input type="number" step="any" name="latitude" id="editLat" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-blue-200 mb-1">Longitude</label>
                    <input type="number" step="any" name="longitude" id="editLng" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs text-blue-200 mb-1"><?= __('status') ?></label>
                <select name="status" id="editStatus" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
                    <option value="active"><?= __('status_active') ?></option>
                    <option value="inactive"><?= __('status_inactive') ?></option>
                    <option value="maintenance"><?= __('status_maintenance') ?></option>
                </select>
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

<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
const deleteMsg = <?= json_encode(__('delete_station_confirm')) ?>;
function openEdit(s) {
    document.getElementById('editId').value = s.id;
    document.getElementById('editName').value = s.name;
    document.getElementById('editLocation').value = s.location || '';
    document.getElementById('editAddress').value = s.address || '';
    document.getElementById('editLat').value = s.latitude || '';
    document.getElementById('editLng').value = s.longitude || '';
    document.getElementById('editStatus').value = s.status;
    document.getElementById('modalEdit').classList.remove('hidden');
}
function confirmDelete(id, name) {
    if (confirm(deleteMsg.replace('%s', name))) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php layoutFoot(); ?>
