<?php
/**
 * Oyejo Gas - application bootstrap.
 *
 * Every portal page starts with:
 *     require_once __DIR__ . '/../includes/bootstrap.php';
 * (adjust the relative path to depth). This file must never be hit directly.
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

define('OYEJO_BOOT', true);
define('OYEJO_VERSION', '0.30.0'); // platform version add-ons declare against

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/addons.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/daily.php';
require_once __DIR__ . '/wishlist.php';

// --- Uninstalled apps go to the installer (Phase 4) ---
if (PHP_SAPI !== 'cli' && !defined('OYEJO_SKIP_INSTALL_CHECK')) {
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $inInstaller = stripos($scriptPath, '/install/') !== false;
    if (!$inInstaller && !is_file(STORAGE_PATH . '/install.lock')) {
        header('Location: ' . url('install/'));
        exit;
    }
}

// --- Secure session defaults (full auth arrives in Phase 5) ---
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Session tick: idle/absolute expiry + rotation (Phase 5) ---
auth_tick();

// --- Daily rewards schema (existing installs) ---
if (PHP_SAPI === 'cli' || is_file(STORAGE_PATH . '/install.lock')) {
    daily_ensure_schema();
    wish_ensure_schema();
}

// --- Maintenance mode (Phase 23): locked storefront, staff exempt ---
oyejo_maintenance_gate();

// --- Central error handling (Phase 25): refs, safe pages, fatal capture ---
set_exception_handler(function ($e) {
    $ref = report_error(err_domain_for($e), 'critical', $e);
    if (!APP_DEBUG) {
        show_error(500, null, $ref);
    }
    // Debug mode: show the error via PHP's own display.
    http_response_code(500);
    echo '<pre style="padding:20px">Uncaught ' . htmlspecialchars(get_class($e)) . ': '
        . htmlspecialchars($e->getMessage()) . "\n"
        . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    exit;
});

// Fatal shutdown capture (E_ERROR/E_PARSE bypass the handler above).
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $ref = report_error('system', 'critical', 'Fatal: ' . $e['message'], ['file' => $e['file'], 'line' => $e['line']]);
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    if (!APP_DEBUG) {
        http_response_code(500);
        $p = BASE_PATH . '/errors/500.php';
        if (is_readable($p)) {
            include $p;
        } else {
            echo 'Something went wrong. Please try again later.';
        }
    }
});

// --- CSRF helpers ---
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && is_string($token)
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

// --- Auth helpers (backed by includes/auth.php since Phase 5) ---
function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return current_user() !== null;
}

function require_login() {
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect(url('customer/login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
}

// NOTE: has_permission()/require_permission() live in includes/rbac.php
// (real permission-level enforcement since Phase 6).

// --- Feature toggles (admin center arrives in Phase 23) ---
function oyejo_maintenance_gate() {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!oyejo_feature('maintenance_mode')) {
        return;
    }
    $path = str_replace(chr(92), '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (stripos($path, '/install/') !== false || stripos($path, '/admin/') !== false) {
        return;
    }
    if (preg_match('#/customer/login\.php$#i', $path)) {
        return;
    }
    if (preg_match('#\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|webmanifest)$#i', $path)) {
        return;
    }
    try {
        $u = current_user();
        if ($u && has_permission('portal.admin')) {
            return;
        }
    } catch (Throwable $t) {
        // Fall through to the 503 page.
    }
    http_response_code(503);
    header('Retry-After: 3600');
    $p = BASE_PATH . '/errors/503.php';
    if (is_readable($p)) {
        include $p;
    } else {
        echo 'Scheduled maintenance is in progress. Please check back soon.';
    }
    exit;
}

function oyejo_default_features() {
    return [
        'customer_registration' => true,
        'guest_checkout' => true,
        'product_ordering' => true,
        'gas_refills' => true,
        'cylinder_pickups' => true,
        'cylinder_exchange' => true,
        'delivery_service' => true,
        'driver_portal' => true,
        'online_payments' => true,
        'bank_transfers' => true,
        'cash_on_delivery' => true,
        'customer_wallet' => true,
        'coupons' => true,
        'reviews' => true,
        'support_tickets' => true,
        'referrals' => true,
        'spin_to_win' => true,
        'daily_rewards' => true,
        'email_notifications' => true,
        'sms_notifications' => true,
        'whatsapp_notifications' => true,
        'push_notifications' => true,
        'pwa_installation' => true,
        'marketing_campaigns' => true,
        'addons' => true,
        'maintenance_mode' => false,
    ];
}

/**
 * Server-side feature flag. Reads DB overrides when the Phase 3/23
 * `feature_toggles` table exists; otherwise uses safe defaults.
 */
function oyejo_feature($key) {
    static $overrides = null;
    if ($overrides === null) {
        $overrides = [];
        try {
            $rows = db()->query('SELECT `key`, `enabled` FROM feature_toggles')->fetchAll();
            foreach ($rows as $row) {
                $overrides[$row['key']] = (bool) $row['enabled'];
            }
        } catch (Throwable $t) {
            // Table not installed yet - fall through to defaults.
        }
    }
    if (array_key_exists($key, $overrides)) {
        return $overrides[$key];
    }
    $defaults = oyejo_default_features();
    return $defaults[$key] ?? false;
}

/** Block access when a feature is switched OFF (404: hidden as if absent). */
function require_feature($key) {
    if (!oyejo_feature($key)) {
        http_response_code(404);
        $p = BASE_PATH . '/errors/404.php';
        if (is_readable($p)) {
            include $p;
        } else {
            echo 'Not available.';
        }
        exit;
    }
}
