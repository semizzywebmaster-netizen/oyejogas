<?php
/**
 * Oyejo Gas - Paystack / Opay webhooks.
 * Secrets never leave the environment. Paystack uses x-paystack-signature
 * (HMAC-SHA512 of the raw body). Opay uses HMAC-SHA512 of the JSON body
 * with the merchant private key.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/payments.php';

header('Content-Type: application/json');
if (request_method() !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$gw = strtolower(trim((string) ($_GET['gateway'] ?? '')));
$psig = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');

if ($psig !== '' || $gw === 'paystack') {
    $secret = trim((string) env('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: ''));
    if ($secret === '') {
        $secret = trim((string) env('GATEWAY_SECRET_KEY', ''));
    }
    if ($secret === '') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Paystack not configured']);
        exit;
    }
    $expect = hash_hmac('sha512', (string) $raw, $secret);
    if ($psig === '' || !hash_equals($expect, $psig)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Bad signature']);
        exit;
    }
    $body = json_decode((string) $raw, true);
    $event = strtolower((string) ($body['event'] ?? ''));
    $ref = (string) ($body['data']['reference'] ?? '');
    $status = strtolower((string) ($body['data']['status'] ?? ''));
    $p = $ref !== '' ? pay_by_reference($ref) : null;
    if (!$p) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Payment not found']);
        exit;
    }
    if ($p['status'] !== 'pending') {
        echo json_encode(['ok' => true, 'already' => $p['status']]);
        exit;
    }
    if ($event === 'charge.success' || $status === 'success') {
        pay_mark_gateway((int) $p['id'], 'paystack', $ref);
        [$ok, $msg] = pay_verify((int) $p['id'], true, null, 'paystack webhook');
    } else {
        [$ok, $msg] = pay_verify((int) $p['id'], false, null, 'paystack ' . substr($event ?: $status, 0, 40));
    }
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

// Opay (or legacy HMAC JSON {reference,status,signature})
$prv = trim((string) env('OPAY_PRIVATE_KEY', getenv('OPAY_PRIVATE_KEY') ?: ''));
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

if ($gw === 'opay' || $prv !== '') {
    if ($prv === '') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Opay not configured']);
        exit;
    }
    $hdr = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_SIGNATURE'] ?? '');
    $hdr = preg_replace('/^Bearer\s+/i', '', $hdr);
    $expect = hash_hmac('sha512', (string) $raw, $prv);
    $payloadSig = (string) ($body['sha512'] ?? $body['signature'] ?? $hdr);
    if ($payloadSig === '' || !hash_equals($expect, $payloadSig)) {
        // Some Opay payloads sign the inner payload only.
        $inner = isset($body['payload']) ? json_encode($body['payload'], JSON_UNESCAPED_SLASHES) : '';
        $okInner = $inner !== '' && hash_equals(hash_hmac('sha512', $inner, $prv), $payloadSig);
        if (!$okInner) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Bad signature']);
            exit;
        }
    }
    $ref = (string) ($body['payload']['reference'] ?? $body['reference'] ?? '');
    $status = strtoupper((string) ($body['payload']['status'] ?? $body['status'] ?? ''));
    $p = $ref !== '' ? pay_by_reference($ref) : null;
    if (!$p) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Payment not found']);
        exit;
    }
    if ($p['status'] !== 'pending') {
        echo json_encode(['ok' => true, 'already' => $p['status']]);
        exit;
    }
    if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'], true)) {
        pay_mark_gateway((int) $p['id'], 'opay', $ref);
        [$ok, $msg] = pay_verify((int) $p['id'], true, null, 'opay webhook');
    } else {
        [$ok, $msg] = pay_verify((int) $p['id'], false, null, 'opay ' . substr($status, 0, 40));
    }
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

// Legacy signed JSON used in tests.
$secret = getenv('PAYSTACK_SECRET_KEY') ?: getenv('GATEWAY_SECRET_KEY') ?: '';
if ($secret === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Gateway not configured']);
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
$p = pay_by_reference($ref);
if (!$p || $p['method'] !== 'online') {
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
