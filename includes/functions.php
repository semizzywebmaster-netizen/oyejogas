<?php
/**
 * Oyejo Gas - shared helpers (escaping, URLs, redirects, flashes, money).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** HTML-escape. */
function e($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Absolute URL to an app path. */
function url($path = '') {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Base-aware relative URL for local assets/links (subdir-safe). */
function asset($path) {
    $b = rtrim(APP_BASE, '/');
    return ($b === '' ? '' : $b) . '/' . ltrim($path, '/');
}

function redirect($to, $code = 302) {
    header('Location: ' . $to, true, $code);
    exit;
}

/** One-shot flash messages (shown once by the header). */
function flash($type, $msg) {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes() {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/**
 * Format integer minor units (kobo) as Naira. Money is ALWAYS stored and
 * calculated as integers - never floats (see checklist D-34).
 */
function format_money($minor, $symbol = '₦') {
    return $symbol . number_format(((int) $minor) / 100, 2);
}

function request_method() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function post($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Entry pages accept no PATH_INFO. Serves our 404 page for /index.php/xyz
 * style URLs (and dev-server fallbacks) instead of rendering the page.
 */
function reject_path_info() {
    $pi = $_SERVER['PATH_INFO'] ?? '';
    if ($pi !== '' && $pi !== '/') {
        http_response_code(404);
        $p = BASE_PATH . '/errors/404.php';
        if (is_readable($p)) {
            include $p;
        } else {
            echo 'Not found.';
        }
        exit;
    }
}
