<?php
/**
 * Oyejo Gas - start Paystack / Opay checkout for a pending online payment.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$order_id = (int) ($_GET['order'] ?? post('order_id', 0));
$pay_id = (int) ($_GET['payment'] ?? post('payment_id', 0));

$p = null;
if ($pay_id > 0) {
    $p = pay_get($pay_id);
} elseif ($order_id > 0) {
    $s = db()->prepare(
        "SELECT * FROM `payments` WHERE `order_id` = ? AND `customer_id` = ? AND `method` = 'online' ORDER BY `id` DESC LIMIT 1"
    );
    $s->execute([$order_id, $cid]);
    $p = $s->fetch() ?: null;
}
if (!$p || (int) $p['customer_id'] !== $cid) {
    flash('error', 'Payment not found.');
    redirect(url('customer/payments.php'));
}
if ($p['method'] !== 'online' || $p['status'] !== 'pending') {
    flash('info', 'That payment does not need a gateway checkout.');
    redirect(url('customer/payments.php'));
}

$gateways = pay_gateways();
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $gw = strtolower(trim((string) post('gateway', '')));
        if (!isset($gateways[$gw])) {
            $errors[] = 'That payment gateway is not configured.';
        } else {
            [$ok, $msg, $url] = $gw === 'opay'
                ? pay_opay_init($p)
                : pay_paystack_init($p);
            if ($ok && $url) {
                redirect($url);
            }
            $errors[] = $msg;
        }
    }
}

$page_title = 'Pay online';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo;
  <a href="<?= e(url('customer/payments.php')) ?>">Payments</a> &rsaquo; Pay online</p>
<h1>Pay online</h1>
<p class="section-lead"><?= e($p['payment_reference']) ?> · <?= e(format_money((int) $p['amount_minor'])) ?>
  <?php if (!empty($p['order_number'])) : ?> · Order <?= e($p['order_number']) ?><?php endif; ?></p>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if (!$gateways) : ?>
  <div class="card">
    <p>Online gateways are not configured yet. Use bank transfer or wait for staff to confirm.</p>
    <p><a class="btn primary" href="<?= e(url('customer/payments.php')) ?>">Back to payments</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <h2>Choose a gateway</h2>
    <?php foreach ($gateways as $key => $g) : ?>
      <form method="post" action="" class="stack" style="margin-bottom:12px">
        <?= csrf_field() ?>
        <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="order_id" value="<?= (int) ($p['order_id'] ?? 0) ?>">
        <input type="hidden" name="gateway" value="<?= e($key) ?>">
        <p><button class="btn primary" type="submit">Pay with <?= e($g['label']) ?></button></p>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
