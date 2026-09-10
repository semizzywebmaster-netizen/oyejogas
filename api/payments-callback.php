<?php
/**
 * Oyejo Gas - online-gateway webhook (Phase 17, PY-03).
 * POST JSON: {"reference":"PAY-...","status":"success","signature":"..."}
 * signature = HMAC-SHA256(reference|status, gateway secret from environment).
 * Secrets are never in the repo or database (PY-15).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/payments.php';

header('Content-Type: application/json');
if (request_method() !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}
$secret = getenv('PAYSTACK_SECRET_KEY') ?: getenv('GATEWAY_SECRET_KEY') ?: '';
if ($secret === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Gateway not configured']);
    exit;
}
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}
$ref = (string) ($body['reference'] ?? '');
$status = (string) ($body['status'] ?? '');
$sig = (string) ($body['signature'] ?? '');
$expect = hash_hmac('sha256', $ref . '|' . $status, $secret);
if ($ref === '' || !hash_equals($expect, $sig)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Bad signature']);
    exit;
}
$stmt = db()->prepare("SELECT * FROM `payments` WHERE `payment_reference` = ? AND `method` = 'online' LIMIT 1");
$stmt->execute([$ref]);
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Payment not found']);
    exit;
}
if ($p['status'] !== 'pending') {
    echo json_encode(['ok' => true, 'already' => $p['status']]);
    exit;
}
if (strtolower($status) === 'success') {
    [$ok, $msg] = pay_verify((int) $p['id'], true, null, 'gateway webhook');
} else {
    [$ok, $msg] = pay_verify((int) $p['id'], false, null, 'gateway reported ' . substr($status, 0, 60));
}
echo json_encode(['ok' => $ok, 'message' => $msg]);
