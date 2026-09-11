<?php
/**
 * Oyejo Gas - return URL after Paystack / Opay checkout.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$ref = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$gw = strtolower(trim((string) ($_GET['gateway'] ?? '')));

if ($ref === '') {
    flash('error', 'Missing payment reference.');
    redirect(url('customer/payments.php'));
}

$p = pay_by_reference($ref);
if (!$p || (int) $p['customer_id'] !== $cid) {
    flash('error', 'Payment not found.');
    redirect(url('customer/payments.php'));
}

if ($p['status'] === 'verified') {
    flash('success', 'Payment confirmed.');
    redirect(url('customer/payments.php'));
}

$use = $gw !== '' ? $gw : (string) ($p['gateway'] ?? 'paystack');
if ($use === 'opay') {
    [$ok, $msg] = pay_opay_verify($p);
} else {
    [$ok, $msg] = pay_paystack_verify($p);
}
flash($ok ? 'success' : 'error', $msg);
redirect(url('customer/payments.php'));
