<?php
/**
 * Oyejo Gas - central configuration.
 *
 * Loads the .env file (if present), defines path/URL/DB constants and PHP
 * defaults. Requires PHP >= 7.4. No composer dependencies.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

define('BASE_PATH', dirname(__DIR__));

// --- Minimal .env loader (tolerates a missing file) ---
$__env_file = BASE_PATH . '/.env';
if (is_readable($__env_file)) {
    $lines = file($__env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (strlen($v) >= 2 && (
                ($v[0] === '"' && substr($v, -1) === '"') ||
                ($v[0] === "'" && substr($v, -1) === "'")
            )) {
                $v = substr($v, 1, -1);
            }
            if (getenv($k) === false) {
                putenv($k . '=' . $v);
                $_ENV[$k] = $v;
            }
        }
    }
}
unset($line, $lines, $k, $v, $__env_file);

function env($key, $default = null) {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $g = getenv($key);
    return $g === false ? $default : $g;
}

function env_bool($key, $default = false) {
    $v = env($key, $default);
    if (is_bool($v)) {
        return $v;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
}

define('APP_NAME', env('APP_NAME', 'Oyejo Gas'));
define('APP_DEBUG', env_bool('APP_DEBUG', false));
date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Lagos'));

// --- Base URL: explicit APP_URL wins, otherwise auto-detect ---
$__app_url = (string) env('APP_URL', '');
if ($__app_url === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $__app_url = $scheme . '://' . $host . (($dir === '/' || $dir === '.' || $dir === '') ? '' : $dir);
}
define('APP_URL', rtrim($__app_url, '/'));
$__parts = parse_url(APP_URL);
define('APP_BASE', rtrim($__parts['path'] ?? '', '/'));
unset($__app_url, $__parts, $scheme, $host, $script, $dir);

// --- Database credentials (used by config/database.php) ---
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'oyejo_gas'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// --- Filesystem paths ---
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('BACKUP_PATH', STORAGE_PATH . '/backups');

// --- PHP defaults ---
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
