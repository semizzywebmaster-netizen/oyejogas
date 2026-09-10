<?php
/**
 * Oyejo Gas - Web Push subscription endpoint (Phase 27, PW-09).
 * JSON in/out. Actions: save {endpoint, keys:{p256dh, auth}},
 * remove {endpoint}. Logged-in users only, CSRF-checked.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');
function push_api_out($ok, $msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    push_api_out(false, 'POST only.', 405);
}
if (!is_logged_in()) {
    push_api_out(false, 'Login required.', 403);
}
$in = $_POST;
if ($in === []) {
    $raw = json_decode((string) @file_get_contents('php://input'), true);
    if (is_array($raw)) {
        $in = $raw;
    }
}
if (!csrf_verify((string) ($in['csrf_token'] ?? ''))) {
    push_api_out(false, 'Security token mismatch. Reload and try again.', 403);
}
if (!oyejo_feature('push_notifications')) {
    push_api_out(false, 'Push notifications are disabled.', 403);
}
$me = current_user();
$action = (string) ($in['action'] ?? 'save');
if ($action === 'remove') {
    push_unsubscribe((int) $me['id'], (string) ($in['endpoint'] ?? ''));
    push_api_out(true, 'Push notifications disabled on this device.');
}
if ($action !== 'save') {
    push_api_out(false, 'Unknown action.', 400);
}
$keys = (array) ($in['keys'] ?? []);
[$ok, $msg] = push_subscribe((int) $me['id'], (string) ($in['endpoint'] ?? ''),
    (string) ($keys['p256dh'] ?? ''), (string) ($keys['auth'] ?? ''));
push_api_out($ok, $msg, $ok ? 200 : 400);
