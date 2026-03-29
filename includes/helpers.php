<?php

function h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, string $message, string $type = 'success'): void {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $f;
    }
    return null;
}

function flashAlert(string $key): string {
    $f = getFlash($key);
    if (!$f) return '';
    $colors = [
        'success' => 'bg-green-500/20 border-green-400 text-green-300',
        'error'   => 'bg-red-500/20 border-red-400 text-red-300',
        'warning' => 'bg-yellow-500/20 border-yellow-400 text-yellow-300',
        'info'    => 'bg-blue-500/20 border-blue-400 text-blue-300',
    ];
    $icons = [
        'success' => 'check_circle',
        'error'   => 'error',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    $cls  = $colors[$f['type']] ?? $colors['info'];
    $icon = $icons[$f['type']] ?? 'info';
    return "<div class=\"flex items-center gap-2 border rounded-xl px-4 py-3 mb-4 {$cls}\">
                <span class=\"material-icons text-lg\">{$icon}</span>
                <span>" . h($f['message']) . "</span>
            </div>";
}

function controllerStatusBadge(string $status): string {
    $map = [
        'Online'   => ['bg-green-500/20 text-green-300 border-green-500', 'wifi', 'Online'],
        'Offline'  => ['bg-gray-500/20 text-gray-400 border-gray-500', 'wifi_off', 'Offline'],
        'Faulted'  => ['bg-red-500/20 text-red-300 border-red-500', 'error', 'Faulted'],
        'Updating' => ['bg-yellow-500/20 text-yellow-300 border-yellow-500', 'system_update', 'Updating'],
    ];
    [$cls, $icon, $label] = $map[$status] ?? ['bg-gray-500/20 text-gray-400 border-gray-500', 'help', $status];
    return "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium {$cls}\">
                <span class=\"material-icons text-sm\">{$icon}</span>{$label}
            </span>";
}

function connectorStatusBadge(string $status): string {
    $map = [
        'Ready to use'               => ['bg-blue-500/20 text-blue-300 border-blue-500', 'power', 'Ready to use'],
        'Plugged in'                 => ['bg-yellow-500/20 text-yellow-300 border-yellow-500', 'electrical_services', 'Plugged in'],
        'Charging in progress'       => ['bg-green-500/20 text-green-300 border-green-500', 'bolt', 'Charging'],
        'Charging paused by vehicle' => ['bg-orange-500/20 text-orange-300 border-orange-500', 'pause_circle', 'Paused (Vehicle)'],
        'Charging paused by charger' => ['bg-orange-500/20 text-orange-300 border-orange-500', 'pause_circle', 'Paused (Charger)'],
        'Charging finish'            => ['bg-teal-500/20 text-teal-300 border-teal-500', 'check_circle', 'Finished'],
        'Unavailable'                => ['bg-gray-500/20 text-gray-400 border-gray-500', 'block', 'Unavailable'],
    ];
    [$cls, $icon, $label] = $map[$status] ?? ['bg-gray-500/20 text-gray-400 border-gray-500', 'help', $status];
    return "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium {$cls}\">
                <span class=\"material-icons text-sm\">{$icon}</span>{$label}
            </span>";
}

function transactionStatusBadge(string $status): string {
    $map = [
        'Pending'   => ['bg-yellow-500/20 text-yellow-300 border-yellow-500', 'hourglass_empty'],
        'Charging'  => ['bg-green-500/20 text-green-300 border-green-500', 'bolt'],
        'Completed' => ['bg-blue-500/20 text-blue-300 border-blue-500', 'check_circle'],
        'Stopped'   => ['bg-gray-500/20 text-gray-400 border-gray-500', 'stop_circle'],
        'Faulted'   => ['bg-red-500/20 text-red-300 border-red-500', 'error'],
    ];
    [$cls, $icon] = $map[$status] ?? ['bg-gray-500/20 text-gray-400 border-gray-500', 'help'];
    return "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium {$cls}\">
                <span class=\"material-icons text-sm\">{$icon}</span>{$status}
            </span>";
}

function formatDateTH(string $datetime): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($datetime);
    return date('d/m/Y H:i', $ts);
}

function currentUserRole(): string {
    return $_SESSION['user_role'] ?? 'viewer';
}

function isAdmin(): bool {
    return currentUserRole() === 'admin';
}

function isOperator(): bool {
    return in_array(currentUserRole(), ['admin', 'operator']);
}

function paginationLinks(int $total, int $perPage, int $current, string $url): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="flex items-center gap-1 mt-4 flex-wrap">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $current ? 'bg-yellow-400 text-blue-900 font-bold' : 'bg-blue-800 text-gray-300 hover:bg-blue-700';
        $html .= "<a href=\"{$url}&page={$i}\" class=\"px-3 py-1 rounded-lg text-sm {$active} transition\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}
