<?php
// ===============================
// TeaStore configuration
// Update these values manually, or use setup.php once.
// Set SITE_URL_CONFIG to '__AUTO__' to auto-detect the current domain/subfolder.
// ===============================
define('DB_HOST', 'localhost');
define('DB_USER', 'teastore');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'teastore_db');
define('SITE_URL_CONFIG', '__AUTO__');
define('SITE_NAME', 'TeaStore');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

function detectSiteUrl(): string
{
    $configured = trim((string) SITE_URL_CONFIG);
    if ($configured !== '' && $configured !== '__AUTO__') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return rtrim($scheme . '://' . $host, '/');
}


function teastoreDbHasTable(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function teastoreDbTableColumns(PDO $pdo, string $table): array {
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        $cache[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $cache[$key] = [];
    }
    return $cache[$key];
}

function teastoreRefreshTableColumnsCache(string $table): void {
    // helper kept for compatibility; cache is request-scoped so nothing needed here
}

function teastoreDbAddColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    $cols = teastoreDbTableColumns($pdo, $table);
    if (in_array($column, $cols, true)) return;
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    } catch (Throwable $e) {}
}

function teastoreEnsureCriticalSchema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL,
            `setting_value` TEXT NULL,
            `setting_group` VARCHAR(50) DEFAULT 'general',
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
    teastoreDbAddColumnIfMissing($pdo, 'site_settings', 'setting_group', "VARCHAR(50) DEFAULT 'general'");
    teastoreDbAddColumnIfMissing($pdo, 'site_settings', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    try {
        $pdo->exec("ALTER TABLE `site_settings` ADD UNIQUE KEY `uniq_setting_key` (`setting_key`)");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_token` VARCHAR(128) NOT NULL,
            `order_ref` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00,
            `payment_method` VARCHAR(20) DEFAULT 'stripe',
            `payment_intent_id` VARCHAR(255) NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `expires_at` DATETIME NULL,
            `paid_at` DATETIME NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_payment_session_token` (`session_token`),
            KEY `idx_payment_order_ref` (`order_ref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `wishlist` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_product` (`user_id`,`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    // Older installs may miss option columns used by cart/checkout.
    if (teastoreDbHasTable($pdo, 'cart')) {
        teastoreDbAddColumnIfMissing($pdo, 'cart', 'variant_id', 'INT NULL');
        teastoreDbAddColumnIfMissing($pdo, 'cart', 'options_json', 'LONGTEXT NULL');
        teastoreDbAddColumnIfMissing($pdo, 'cart', 'variant_info', 'VARCHAR(255) NULL');
    }
}

function teastoreSafeQueryAll(string $sql, array $params = []): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function teastoreSafeQueryValue(string $sql, array $params = [], $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    } catch (Throwable $e) {
        return $default;
    }
}

if (!defined('SITE_URL')) {
    define('SITE_URL', detectSiteUrl());
}

function site_url(string $path = ''): string
{
    if ($path === '') {
        return SITE_URL;
    }
    return SITE_URL . '/' . ltrim($path, '/');
}


function pretty_url(string $path = ''): string
{
    $path = trim((string)$path);
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim($path, '/');
    $path = preg_replace('#\.php\.php$#i', '.php', $path);

    if ($path === '' || strcasecmp($path, 'index.php') === 0) {
        return SITE_URL . '/';
    }

    if (preg_match('#^admin(?:/index(?:\.php)?)?$#i', $path)) {
        return SITE_URL . '/admin';
    }
    if (preg_match('#^admin/([^/]+?)(?:\.php)?$#i', $path, $m)) {
        $name = preg_replace('#\.php$#i', '', $m[1]);
        return SITE_URL . '/admin/' . $name;
    }
    if (preg_match('#^pages/([^/]+?)(?:\.php)?$#i', $path, $m)) {
        $name = preg_replace('#\.php$#i', '', $m[1]);
        if ($name === 'index' || $name === 'home') {
            return SITE_URL . '/';
        }
        return SITE_URL . '/' . $name;
    }

    $clean = preg_replace('#\.php$#i', '', $path);
    return SITE_URL . '/' . ltrim($clean, '/');
}

function endpoint_url(string $path): string
{
    $path = trim((string)$path);
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim($path, '/');
    if ($path === '') return SITE_URL . '/';
    return SITE_URL . '/' . $path;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    $message = 'Database connection failed. Please check includes/config.php or run setup.php.';

    if (
        !empty($_SERVER['HTTP_ACCEPT'])
        && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
            || (isset($_SERVER['REQUEST_URI']) && preg_match('#/(stripe|paypal|cart-action|wishlist-action)#', $_SERVER['REQUEST_URI'])))
    ) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error'   => $e->getMessage(),
        ]);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Database connection error</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#f7f7f7;padding:40px;color:#222}.box{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.06)}code{background:#f3f4f6;padding:2px 6px;border-radius:6px}</style>'
        . '</head><body><div class="box"><h2>Database connection failed</h2><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Quick fix:</strong> update <code>includes/config.php</code> with your real database host, username, password, and database name, or open <code>setup.php</code> once.</p>'
        . '<p><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p></div></body></html>';
    exit;
}

teastoreEnsureCriticalSchema($pdo);

$GLOBALS['teastore_settings_cache'] = $GLOBALS['teastore_settings_cache'] ?? null;

function getSetting($key, $default = '') {
    global $pdo;
    if (!array_key_exists('teastore_settings_cache', $GLOBALS) || $GLOBALS['teastore_settings_cache'] === null) {
        $GLOBALS['teastore_settings_cache'] = [];
        try {
            if (teastoreDbHasTable($pdo, 'site_settings')) {
                $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
                $GLOBALS['teastore_settings_cache'] = array_column($rows, 'setting_value', 'setting_key');
            }
        } catch (Throwable $e) {
            $GLOBALS['teastore_settings_cache'] = [];
        }
    }
    return $GLOBALS['teastore_settings_cache'][$key] ?? $default;
}

function clearSettingCache(): void {
    $GLOBALS['teastore_settings_cache'] = null;
}

function setSetting($key, $value, ?string $group = null) {
    global $pdo;
    $key = trim((string)$key);
    if ($key === '') return false;

    if (!teastoreDbHasTable($pdo, 'site_settings')) {
        teastoreEnsureCriticalSchema($pdo);
    }

    $columns = teastoreDbTableColumns($pdo, 'site_settings');
    $hasGroup = in_array('setting_group', $columns, true);
    $hasUpdatedAt = in_array('updated_at', $columns, true);
    $group = $group ?: 'general';

    try {
        $existsStmt = $pdo->prepare('SELECT id FROM site_settings WHERE setting_key = ? LIMIT 1');
        $existsStmt->execute([$key]);
        $rowId = $existsStmt->fetchColumn();

        if ($rowId) {
            $setParts = ['setting_value = ?'];
            $params = [$value];
            if ($hasGroup) { $setParts[] = 'setting_group = ?'; $params[] = $group; }
            if ($hasUpdatedAt) { $setParts[] = 'updated_at = NOW()'; }
            $params[] = $key;
            $pdo->prepare('UPDATE site_settings SET ' . implode(', ', $setParts) . ' WHERE setting_key = ?')->execute($params);
        } else {
            $insertCols = ['setting_key', 'setting_value'];
            $insertVals = [$key, $value];
            if ($hasGroup) { $insertCols[] = 'setting_group'; $insertVals[] = $group; }
            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $pdo->prepare('INSERT INTO site_settings (' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')')->execute($insertVals);
        }
    } catch (Throwable $e) {
        // Final fallback for very old schemas.
        try {
            $stmt = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
            $stmt->execute([$value, $key]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $value]);
            }
        } catch (Throwable $e2) {
            throw $e2;
        }
    }

    if (!is_array($GLOBALS['teastore_settings_cache'] ?? null)) {
        $GLOBALS['teastore_settings_cache'] = [];
    }
    $GLOBALS['teastore_settings_cache'][$key] = $value;
    return true;
}

function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isStoreManager() { return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','store_manager']); }
function canAccessAdmin() { return isStoreManager(); }
function redirect($url) {
    $url = (string)$url;
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safe . '"></noscript>';
    exit;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim((string) $str)), ENT_QUOTES, 'UTF-8');
}

function flash($key, $msg = null) {
    if ($msg === null) {
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    $_SESSION['flash'][$key] = $msg;
}

function formatPrice($price) {
    $sym = getSetting('currency_symbol', '$');
    return $sym . number_format((float) $price, 2);
}


function getWishlistCount(): int {
    global $pdo;
    if (!isLoggedIn()) return 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        return 0;
    }
}

function getCartCount() {
    global $pdo;
    $sid = session_id();
    $uid = $_SESSION['user_id'] ?? null;
    try {
        if ($uid) {
            $stmt = $pdo->prepare('SELECT SUM(qty) FROM cart WHERE user_id = ?');
            $stmt->execute([$uid]);
        } else {
            $stmt = $pdo->prepare('SELECT SUM(qty) FROM cart WHERE session_id = ?');
            $stmt->execute([$sid]);
        }
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function getThemeCssVars() {
    $primary = getSetting('theme_primary_color', '#eb1700');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
        $primary = '#eb1700';
    }

    $mode = getSetting('theme_mode', 'light');
    $font = getSetting('theme_font', 'DM Sans');

    $r = hexdec(substr($primary, 1, 2));
    $g = hexdec(substr($primary, 3, 2));
    $b = hexdec(substr($primary, 5, 2));

    $dark  = sprintf('#%02x%02x%02x', max(0, $r - 30), max(0, $g - 30), max(0, $b - 30));
    $light = sprintf('#%02x%02x%02x', min(255, $r + 40), min(255, $g + 40), min(255, $b + 40));
    $alpha = sprintf('rgba(%d,%d,%d,0.1)', $r, $g, $b);

    if ($mode === 'dark') {
        $textColor = '#f1f1f1';
        $bgColor = '#121212';
        $cardBg = '#1e1e1e';
        $borderColor = '#333333';
        $textLight = '#aaaaaa';
        $textMuted = '#777777';
        $bodyBg = '#121212';
    } else {
        $textColor = '#1a1a1a';
        $bgColor = '#f8f8f8';
        $cardBg = '#ffffff';
        $borderColor = '#e8e8e8';
        $textLight = '#6b6b6b';
        $textMuted = '#9b9b9b';
        $bodyBg = '#ffffff';
    }

    $css = "
    :root {
        --primary: {$primary};
        --primary-dark: {$dark};
        --primary-light: {$light};
        --primary-alpha: {$alpha};
        --text: {$textColor};
        --text-light: {$textLight};
        --text-muted: {$textMuted};
        --border: {$borderColor};
        --bg: {$bgColor};
        --white: {$cardBg};
        --body-bg: {$bodyBg};
        --font-family: '{$font}', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    body { font-family: var(--font-family); background: var(--body-bg); }
    ";

    $hoverStyle = getSetting('card_hover_style', 'primary');
    if ($hoverStyle === 'subtle') {
        $css .= '.product-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.08)!important;border-color:#d1d5db!important;transform:translateY(-2px)!important;}';
    } elseif ($hoverStyle === 'shadow') {
        $css .= '.product-card:hover{box-shadow:0 8px 28px rgba(0,0,0,0.15)!important;border-color:var(--border)!important;transform:translateY(-3px)!important;}';
    } elseif ($hoverStyle === 'none') {
        $css .= '.product-card:hover{transform:none!important;box-shadow:var(--shadow)!important;border-color:var(--border)!important;}';
    }

    // Product hover action visibility
    $hoverAction = getSetting('product_hover_action', 'both');
    if ($hoverAction === 'quick_add') {
        $css .= '.product-hover-btn.btn-view-detail{display:none!important;}';
    } elseif ($hoverAction === 'view_details') {
        $css .= '.product-hover-btn.btn-quick-add{display:none!important;}';
    } elseif ($hoverAction === 'none') {
        $css .= '.product-hover-overlay{display:none!important;}.product-plus-btn{display:flex!important;}';
    }

    // Product grid columns from settings
    $homeCols   = max(2, min(6, (int)(getSetting('home_product_cols', '4'))));
    $shopCols   = max(2, min(6, (int)(getSetting('shop_product_cols', '4'))));
    $homePerRow = max(1, min(8, (int)(getSetting('home_products_per_section', '8'))));
    $shopPerPage= max(4, min(48, (int)(getSetting('shop_per_page', '8'))));

    $css .= ".product-grid.home-grid{grid-template-columns:repeat({$homeCols},1fr)!important;}";
    $css .= ".product-grid.shop-grid{grid-template-columns:repeat({$shopCols},1fr)!important;}";
    $css .= "@media(max-width:768px){.product-grid.home-grid,.product-grid.shop-grid{grid-template-columns:repeat(2,1fr)!important;}}";
    $css .= "@media(max-width:480px){.product-grid.home-grid,.product-grid.shop-grid{grid-template-columns:repeat(2,1fr)!important;}}";

    return $css;
}
