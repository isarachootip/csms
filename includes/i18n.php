<?php
/**
 * i18n – Internationalization helper
 * Supported: th (Thai), en (English), zh (Chinese)
 */

define('SUPPORTED_LANGS', ['th', 'en', 'zh']);
define('DEFAULT_LANG', 'th');

// Language metadata (flag emoji, native name, html lang attr)
define('LANG_META', [
    'th' => ['flag' => '🇹🇭', 'name' => 'ภาษาไทย',  'html' => 'th'],
    'en' => ['flag' => '🇬🇧', 'name' => 'English',   'html' => 'en'],
    'zh' => ['flag' => '🇨🇳', 'name' => '中文',       'html' => 'zh-CN'],
]);

// Set active language from: GET param → session → default
function i18n_init(): void {
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS)) {
        $_SESSION['lang'] = $_GET['lang'];
        // Redirect without ?lang= to keep URLs clean
        $params = $_GET;
        unset($params['lang']);
        $qs  = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . $qs);
        exit;
    }
    if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'], SUPPORTED_LANGS)) {
        $_SESSION['lang'] = DEFAULT_LANG;
    }
}

// Load translations for active language
function i18n_load(): array {
    static $translations = null;
    if ($translations === null) {
        $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $translations = file_exists($file) ? require $file : require __DIR__ . '/../lang/' . DEFAULT_LANG . '.php';
    }
    return $translations;
}

// Translate a key (with optional sprintf args)
function __(string $key, ...$args): string {
    $t   = i18n_load();
    $str = $t[$key] ?? $key;
    return $args ? sprintf($str, ...$args) : $str;
}

// Current language code
function lang(): string {
    return $_SESSION['lang'] ?? DEFAULT_LANG;
}

// Render the language switcher dropdown HTML
function langSwitcher(string $currentPage = ''): string {
    $current = lang();
    $meta    = LANG_META;
    $cur     = $meta[$current];

    // Build query string preserving current params
    $params  = $_GET;
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

    $options = '';
    foreach (SUPPORTED_LANGS as $code) {
        if ($code === $current) continue;
        $m       = $meta[$code];
        $params['lang'] = $code;
        $url     = $baseUrl . '?' . http_build_query($params);
        $options .= <<<HTML
        <a href="{$url}"
           class="flex items-center gap-2 px-4 py-2 hover:bg-blue-700/50 text-gray-200 hover:text-yellow-400 transition text-sm whitespace-nowrap">
            <span>{$m['flag']}</span> <span>{$m['name']}</span>
        </a>
        HTML;
    }

    return <<<HTML
    <div class="relative group" id="langDropdown">
        <button type="button"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-800/60 hover:bg-blue-700/60 border border-blue-700/50 text-sm text-gray-200 hover:text-yellow-400 transition focus:outline-none"
            onclick="this.nextElementSibling.classList.toggle('hidden')">
            <span class="text-base">{$cur['flag']}</span>
            <span class="hidden sm:inline">{$cur['name']}</span>
            <span class="material-icons text-base">expand_more</span>
        </button>
        <div class="hidden absolute right-0 mt-1 bg-blue-900/95 backdrop-blur-sm border border-blue-700/50 rounded-xl shadow-xl z-50 overflow-hidden min-w-max">
            {$options}
        </div>
    </div>
    <script>
    document.addEventListener('click', function(e) {
        const d = document.getElementById('langDropdown');
        if (d && !d.contains(e.target)) {
            const menu = d.querySelector('div');
            if (menu) menu.classList.add('hidden');
        }
    });
    </script>
    HTML;
}
