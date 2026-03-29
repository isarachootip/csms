<?php

function layoutHead(string $titleKey = ''): void {
    $langMeta  = LANG_META[lang()];
    $htmlLang  = $langMeta['html'];
    $pageTitle = $titleKey ? h(__($titleKey)) . ' | ' . APP_NAME : __('app_name') . ' – ' . __('app_subtitle');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="{$htmlLang}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$pageTitle}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700;800&family=Noto+Sans+SC:wght@300;400;500;700&display=swap">
        <style>
            * { font-family: 'Sarabun', 'Inter', 'Noto Sans SC', sans-serif; }
            body { background: #0a1628; color: #e2e8f0; }
            .gradient-navy { background: linear-gradient(135deg, #0a1628 0%, #0f2040 50%, #162952 100%); }
            .glass-card {
                background: rgba(15,32,64,0.7);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(59,130,246,0.2);
            }
            .btn-primary {
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: #0a1628; font-weight: 700; transition: all 0.2s;
            }
            .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,0.4); }
            .btn-blue {
                background: linear-gradient(135deg, #1d4ed8, #1e40af);
                color: #fff; font-weight: 600; transition: all 0.2s;
            }
            .btn-blue:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(29,78,216,0.4); }
            .btn-danger { background: linear-gradient(135deg,#ef4444,#dc2626); color:#fff; font-weight:600; transition:all 0.2s; }
            .btn-danger:hover { transform: translateY(-1px); }
            .input-field {
                background: rgba(10,22,40,0.9);
                border: 1px solid rgba(59,130,246,0.3);
                color: #e2e8f0; transition: border-color 0.2s;
            }
            .input-field:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.15); }
            .input-field::placeholder { color: #475569; }
            .nav-item { transition: all 0.2s; }
            .nav-item:hover, .nav-item.active {
                background: rgba(245,158,11,0.15); color: #f59e0b;
                border-left: 3px solid #f59e0b;
            }
            .charging-animation { animation: charging 1.5s ease-in-out infinite alternate; }
            @keyframes charging {
                from { opacity:0.6; transform:scale(0.95); }
                to   { opacity:1;   transform:scale(1.05); }
            }
            .sidebar { transition: transform 0.3s ease; }
            @media (max-width:768px) { .sidebar-hidden { transform: translateX(-100%); } }
            .table-row { transition: background 0.15s; }
            .table-row:hover { background: rgba(59,130,246,0.08); }
            ::-webkit-scrollbar { width:6px; height:6px; }
            ::-webkit-scrollbar-track { background:#0a1628; }
            ::-webkit-scrollbar-thumb { background:#1e3a6e; border-radius:3px; }
            ::-webkit-scrollbar-thumb:hover { background:#3b82f6; }
        </style>
    </head>
    <body class="gradient-navy min-h-screen">
    HTML;
}

function layoutNav(string $active = ''): void {
    $userName = h($_SESSION['user_name'] ?? 'User');
    $role     = h($_SESSION['user_role'] ?? '');

    $navItems = [
        ['href' => 'dashboard.php',    'icon' => 'dashboard',    'label' => __('nav_dashboard')],
        ['href' => 'stations.php',     'icon' => 'ev_station',   'label' => __('nav_stations')],
        ['href' => 'chargers.php',     'icon' => 'electrical_services', 'label' => __('chargers_title')],
        ['href' => 'customers.php',    'icon' => 'people',       'label' => __('nav_customers')],
        ['href' => 'transactions.php', 'icon' => 'receipt_long', 'label' => __('nav_transactions')],
        ['href' => 'settings.php',     'icon' => 'tune',         'label' => __('nav_settings')],
    ];

    $navHtml = '';
    foreach ($navItems as $item) {
        $isActive = basename($active) === $item['href'] ? 'active' : '';
        $navHtml .= <<<HTML
        <a href="{$item['href']}" class="nav-item {$isActive} flex items-center gap-3 px-4 py-3 rounded-xl text-gray-300 font-medium">
            <span class="material-icons text-xl">{$item['icon']}</span>
            <span>{$item['label']}</span>
        </a>
        HTML;
    }

    $logoutLabel    = __('logout');
    $langSwitcher   = langSwitcher($active);
    $appName        = __('app_name');
    $appSubtitle    = __('app_subtitle');
    $langLabel      = __('language');

    echo <<<HTML
    <!-- Mobile Header -->
    <header class="md:hidden fixed top-0 left-0 right-0 z-50 glass-card border-b border-blue-800/50 flex items-center justify-between px-4 py-3">
        <button onclick="toggleSidebar()" class="text-yellow-400 focus:outline-none">
            <span class="material-icons text-2xl">menu</span>
        </button>
        <div class="flex items-center gap-2">
            <span class="material-icons text-yellow-400 text-2xl">bolt</span>
            <span class="font-bold text-yellow-400 text-lg">{$appName}</span>
        </div>
        <div class="flex items-center gap-2">
            {$langSwitcher}
        </div>
    </header>

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" onclick="toggleSidebar()" class="md:hidden fixed inset-0 bg-black/50 z-40 hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-hidden md:translate-x-0 fixed md:sticky top-0 left-0 h-screen w-64 z-50 flex flex-col glass-card border-r border-blue-800/40 overflow-y-auto">
        <!-- Logo + Lang Switcher -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-blue-800/40">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-lg">
                    <span class="material-icons text-blue-900 text-xl">bolt</span>
                </div>
                <div>
                    <p class="font-extrabold text-yellow-400 text-lg leading-none">{$appName}</p>
                    <p class="text-xs text-blue-300 leading-tight">{$appSubtitle}</p>
                </div>
            </div>
        </div>

        <!-- Language Switcher in Sidebar -->
        <div class="px-4 pt-3 pb-1">
            <p class="text-xs text-blue-400 mb-1.5 flex items-center gap-1">
                <span class="material-icons text-sm">language</span> {$langLabel}
            </p>
            {$langSwitcher}
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-3 py-3 space-y-1">
            {$navHtml}
        </nav>

        <!-- User Info -->
        <div class="px-4 py-4 border-t border-blue-800/40">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-sm font-bold text-yellow-400">
                    {$userName[0]}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{$userName}</p>
                    <p class="text-xs text-blue-300 capitalize">{$role}</p>
                </div>
            </div>
            <a href="logout.php" class="flex items-center gap-2 text-sm text-gray-400 hover:text-red-400 transition px-2 py-1 rounded-lg hover:bg-red-500/10">
                <span class="material-icons text-base">logout</span> {$logoutLabel}
            </a>
        </div>
    </aside>

    <script>
    function toggleSidebar() {
        const s = document.getElementById('sidebar');
        const o = document.getElementById('sidebarOverlay');
        s.classList.toggle('sidebar-hidden');
        o.classList.toggle('hidden');
    }
    </script>
    HTML;
}

function layoutFoot(): void {
    echo <<<HTML
    <script>
    document.querySelectorAll('[data-autohide]').forEach(el => {
        setTimeout(() => el.style.opacity = '0', 4000);
        setTimeout(() => el.remove(), 4500);
    });
    </script>
    </body></html>
    HTML;
}
