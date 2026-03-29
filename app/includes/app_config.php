<?php
/**
 * Customer App — Shared Config & Helpers
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

define('APP_BASE',  dirname(__DIR__, 2));
define('CAPP_URL',  APP_URL . '/app');
define('CAPP_NAME', 'EV Charge');

// ── Session helpers
function capp_auth(): bool {
    return !empty($_SESSION['capp_customer_id']);
}

function capp_customer_id(): int {
    return (int)($_SESSION['capp_customer_id'] ?? 0);
}

function capp_require_auth(): void {
    if (!capp_auth()) {
        header('Location: ' . CAPP_URL . '/login.php');
        exit;
    }
}

function capp_customer(): array {
    static $c = null;
    if ($c === null && capp_auth()) {
        $c = DB::fetchOne(
            "SELECT c.*, ct.name AS car_name, ct.brand AS car_brand,
                    ct.connector_type, ct.battery_kwh,
                    w.balance AS wallet_balance,
                    (SELECT COUNT(*) FROM customer_notifications WHERE customer_id=c.id AND read_at IS NULL) AS unread_notifs
             FROM customers c
             LEFT JOIN car_types ct ON ct.id = c.car_type_id
             LEFT JOIN wallet_accounts w ON w.customer_id = c.id
             WHERE c.id = ?",
            [capp_customer_id()]
        );
        if (!$c) $c = [];
    }
    return $c ?: [];
}

// ── Flash message helpers
function capp_flash(string $key, string $msg, string $type = 'success'): void {
    $_SESSION['capp_flash'][$key] = ['msg' => $msg, 'type' => $type];
}

function capp_get_flash(string $key): array {
    $f = $_SESSION['capp_flash'][$key] ?? [];
    unset($_SESSION['capp_flash'][$key]);
    return $f;
}

function capp_flash_html(string $key): string {
    $f = capp_get_flash($key);
    if (!$f) return '';
    $colors = [
        'success' => 'bg-green-500/20 border-green-500/50 text-green-300',
        'error'   => 'bg-red-500/20 border-red-500/50 text-red-300',
        'info'    => 'bg-blue-500/20 border-blue-500/50 text-blue-300',
        'warning' => 'bg-yellow-500/20 border-yellow-500/50 text-yellow-300',
    ];
    $cls = $colors[$f['type']] ?? $colors['info'];
    $icon = match($f['type']) { 'success' => 'check_circle', 'error' => 'error', 'warning' => 'warning', default => 'info' };
    return "<div class='flex items-center gap-2 p-3 rounded-xl border {$cls} text-sm mb-4'>
                <span class='material-icons text-base'>{$icon}</span>
                <span>" . htmlspecialchars($f['msg']) . "</span>
            </div>";
}

// ── Format helpers
function fmt_thb(float $v): string { return number_format($v, 2); }
function fmt_kwh(float $v): string { return number_format($v, 2); }
function fmt_dur(int $min): string {
    if ($min < 60) return "{$min} นาที";
    $h = intdiv($min, 60); $m = $min % 60;
    return $m > 0 ? "{$h} ชม. {$m} นาที" : "{$h} ชม.";
}
function ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'เมื่อกี้';
    if ($diff < 3600) return intdiv($diff, 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return intdiv($diff, 3600) . ' ชม.ที่แล้ว';
    return date('d/m/Y', strtotime($dt));
}
function connector_icon(string $type): string {
    return match($type) {
        'CCS1','CCS2' => '⚡',
        'CHAdeMO'     => '🔌',
        'Type1','Type2'=> '🔋',
        'GB/T'        => '🇨🇳',
        default       => '🔌',
    };
}
function status_color_class(string $s): string {
    return match($s) {
        'Ready to use'           => 'bg-green-500',
        'Charging in progress'   => 'bg-blue-500 animate-pulse',
        'Plugged in'             => 'bg-yellow-400',
        'Charging finish'        => 'bg-purple-500',
        'Unavailable'            => 'bg-gray-500',
        default                  => 'bg-red-500',
    };
}
function tier_info(float $spend): array {
    if ($spend >= 20000) return ['Platinum', '💎', 'from-purple-600 to-purple-800', 10];
    if ($spend >= 5000)  return ['Gold',     '🥇', 'from-yellow-500 to-yellow-700',  5];
    if ($spend >= 1000)  return ['Silver',   '🥈', 'from-gray-400 to-gray-600',       2];
    return                      ['Bronze',   '🥉', 'from-amber-600 to-amber-800',     0];
}
