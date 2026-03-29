<?php
/**
 * Customer App — Shared Layout (Mobile-first)
 */
function capp_head(string $title = '', string $extraHead = ''): void {
    $t = $title ? htmlspecialchars($title) . ' — ' . CAPP_NAME : CAPP_NAME . ' · EV Charging';
    echo <<<HTML
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a1628">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{$t}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Round">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Noto+Sans+SC:wght@400;700&display=swap">
    <style>
        * { font-family: 'Sarabun', 'Noto Sans SC', sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background: #0a1628; color: #e2e8f0; overflow-x: hidden; }
        .glass { background: rgba(15,32,64,0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(59,130,246,0.2); }
        .glass-dark { background: rgba(5,15,35,0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(59,130,246,0.15); }
        .btn-primary { background: linear-gradient(135deg,#f59e0b,#d97706); color:#0a1628; font-weight:700; transition:all .2s; }
        .btn-primary:active { transform:scale(.97); }
        .btn-blue { background: linear-gradient(135deg,#1d4ed8,#1e40af); color:#fff; font-weight:600; transition:all .2s; }
        .btn-blue:active { transform:scale(.97); }
        .btn-green { background: linear-gradient(135deg,#16a34a,#15803d); color:#fff; font-weight:600; transition:all .2s; }
        .btn-red { background: linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; font-weight:600; transition:all .2s; }
        .input-field { background:rgba(10,22,40,.9); border:1px solid rgba(59,130,246,.3); color:#e2e8f0; transition:border .2s; }
        .input-field:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
        .input-field::placeholder { color:#4b6382; }
        .card-glow { box-shadow: 0 4px 30px rgba(59,130,246,.15); }
        .status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; }
        .bottom-nav { position:fixed; bottom:0; left:0; right:0; z-index:100; background:rgba(5,15,35,.95); backdrop-filter:blur(20px); border-top:1px solid rgba(59,130,246,.2); padding-bottom:env(safe-area-inset-bottom,0); }
        .bottom-nav a { flex:1; display:flex; flex-direction:column; align-items:center; padding:.5rem .25rem; font-size:.65rem; color:#64748b; transition:color .2s; }
        .bottom-nav a.active, .bottom-nav a:hover { color:#f59e0b; }
        .bottom-nav .material-icons-round { font-size:1.5rem; margin-bottom:2px; }
        .page-content { padding-bottom: 80px; min-height: 100vh; }
        .top-bar { position:sticky; top:0; z-index:50; background:rgba(5,15,35,.9); backdrop-filter:blur(16px); border-bottom:1px solid rgba(59,130,246,.15); }
        @keyframes pulse-charge { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.7;transform:scale(1.05)} }
        .charging-pulse { animation: pulse-charge 1.5s ease-in-out infinite; }
        @keyframes slide-up { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .slide-up { animation: slide-up .3s ease-out; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(59,130,246,.3); border-radius:4px; }
        .badge-pill { display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:600; }
        .connector-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:8px;font-size:.7rem;border:1px solid; }
    </style>
    {$extraHead}
</head>
<body>
HTML;
}

function capp_foot(): void {
    echo '</body></html>';
}

function capp_bottom_nav(string $active = 'home'): void {
    $cid = capp_customer_id();
    $unread = 0;
    if ($cid) {
        $r = DB::fetchOne("SELECT COUNT(*) AS n FROM customer_notifications WHERE customer_id=? AND read_at IS NULL", [$cid]);
        $unread = (int)($r['n'] ?? 0);
    }
    $badge = $unread > 0 ? "<span style='position:absolute;top:2px;right:calc(50%-14px);background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;border-radius:999px;padding:0 4px;min-width:16px;text-align:center'>{$unread}</span>" : '';

    $nav = [
        'home'    => ['url' => 'index.php',        'icon' => 'home',            'label' => 'หน้าแรก'],
        'find'    => ['url' => 'stations.php',     'icon' => 'ev_station',      'label' => 'ค้นหา'],
        'charge'  => ['url' => 'charge.php',       'icon' => 'bolt',            'label' => 'ชาร์จ'],
        'history' => ['url' => 'history.php',      'icon' => 'receipt_long',    'label' => 'ประวัติ'],
        'profile' => ['url' => 'profile.php',      'icon' => 'person',          'label' => 'โปรไฟล์'],
    ];

    echo '<nav class="bottom-nav flex">';
    foreach ($nav as $key => $item) {
        $cls = ($key === $active) ? 'active' : '';
        $extraBadge = ($key === 'profile') ? $badge : '';
        echo "<a href='{$item['url']}' class='{$cls}' style='position:relative'>
                  {$extraBadge}
                  <span class='material-icons-round'>{$item['icon']}</span>
                  <span>{$item['label']}</span>
              </a>";
    }
    echo '</nav>';
}

function capp_top_bar(string $title = '', bool $showBack = false, string $rightHtml = ''): void {
    $back = $showBack ? "<a href='javascript:history.back()' class='w-9 h-9 flex items-center justify-center rounded-full hover:bg-blue-800/50 transition'>
                            <span class='material-icons-round text-gray-300'>arrow_back</span>
                         </a>" : "<div class='w-9'></div>";
    $t = $title ? "<h1 class='text-base font-bold text-white'>{$title}</h1>" : "<span class='text-yellow-400 font-bold text-lg tracking-wide'>⚡ " . CAPP_NAME . "</span>";
    $right = $rightHtml ?: '<div class="w-9"></div>';
    echo "<div class='top-bar px-4 py-3 flex items-center gap-3'>
              {$back}
              <div class='flex-1 text-center'>{$t}</div>
              {$right}
          </div>";
}
