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
define('OYEJO_VERSION', '0.6.0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';

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

// --- Safety net for uncaught exceptions (full handler in Phase 25) ---
set_exception_handler(function ($e) {
    error_log('[oyejo] Uncaught ' . get_class($e) . ': ' . $e->getMessage());
    if (!APP_DEBUG) {
        http_response_code(500);
        $p = BASE_PATH . '/errors/500.php';
        if (is_readable($p)) {
            include $p;
        } else {
            echo 'Something went wrong. Please try again later.';
        }
        exit;
    }
    // Debug mode: show the error via PHP's own display.
    http_response_code(500);
    echo '<pre style="padding:20px">Uncaught ' . htmlspecialchars(get_class($e)) . ': '
        . htmlspecialchars($e->getMessage()) . "\n"
        . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    exit;
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

/** Block access when a feature is switched OFF (refined in Phase 23). */
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
