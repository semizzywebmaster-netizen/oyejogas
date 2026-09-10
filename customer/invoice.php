<?php
/**
 * Oyejo Gas - printable invoice / receipt for an owned order.
 * Phase 17: persistent invoice numbers, receipts for paid orders and
 * customer refund requests.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/payments.php';

$logged = is_logged_in();
$me = $logged ? current_user() : null;
$cid = $logged ? cart_customer_id((int) $me['id']) : 0;
$guest_id = (int) ($_SESSION[OYEJO_LAST_ORDER_KEY] ?? 0);
if ($logged) {
    require_permission('shop.order');
}

$id = (int) ($_GET['id'] ?? $_GET['order_id'] ?? 0);
$s = db()->prepare('SELECT * FROM `orders` WHERE `id` = ? LIMIT 1');
$s->execute([$id]);
$o = $s->fetch();
$mine = $o && (($cid > 0 && (int) $o['customer_id'] === $cid) || ($guest_id > 0 && (int) $o['id'] === $guest_id));
if (!$o || !$mine) {
    http_response_code(404);
    $page_title = 'Invoice not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Invoice not found</h1></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$message = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cid > 0) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'refund') {
        [$ok, $msg] = ref_request((int) $o['id'], $_POST['amount'] ?? 0,
            $_POST['reason'] ?? '', $_POST['method'] ?? 'wallet',
            ['customer_id' => $cid, 'user_id' => (int) $me['id'], 'is_staff' => false]);
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$s = db()->prepare('SELECT * FROM `order_items` WHERE `order_id` = ? ORDER BY `id`');
$s->execute([$o['id']]);
$items = $s->fetchAll();
$methods = cart_payment_methods();
$inv = pay_invoice_for_order((int) $o['id']);
$receipt_mode = isset($_GET['receipt']) && $o['payment_status'] === 'paid';
$stmt = db()->prepare('SELECT `payment_reference`, `method` FROM `payments` WHERE `order_id` = ? ORDER BY `id` DESC LIMIT 1');
$stmt->execute([$o['id']]);
$pay = $stmt->fetch();
$stmt = db()->prepare(
    "SELECT `refund_number`, `amount_minor`, `status` FROM `refunds` WHERE `order_id` = ? ORDER BY `id` DESC"
);
$stmt->execute([$o['id']]);
$refunds = $stmt->fetchAll();

$page_title = ($receipt_mode ? 'Receipt ' : 'Invoice ') . ($inv['invoice_number'] ?? ('INV-' . $o['order_number']));
require BASE_PATH . '/includes/header.php';
?>
<p class="no-print"><a href="<?= e(url('customer/orders.php?view=' . $o['id'])) ?>">&larr; Back to order</a></p>
<?php if ($message !== '') : ?><div class="alert alert-success no-print"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error no-print"><?= e($e) ?></div><?php endforeach; ?>
<div class="invoice">
  <h1><?= $receipt_mode ? 'Receipt' : 'Invoice' ?> <?= e($inv['invoice_number'] ?? ('INV-' . $o['order_number'])) ?></h1>
  <p><strong>Oyejo Gas</strong> — Cooking gas, delivered.<br>
    Issued: <?= e(substr($inv['issued_at'] ?? $o['created_at'], 0, 16)) ?> · Order: <?= e($o['order_number']) ?>
    · Status: <?= e(ucfirst($inv['status'] ?? 'issued')) ?></p>
  <p><strong>Bill to:</strong><br><?= nl2br(e((string) $o['address_text'])) ?><br><?= e((string) $o['delivery_phone']) ?></p>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it) : ?>
          <tr><td><?= e($it['name']) ?></td><td><?= (int) $it['qty'] ?></td>
            <td><?= e(format_money($it['unit_price_minor'])) ?></td><td><?= e(format_money($it['total_minor'])) ?></td></tr>
        <?php endforeach; ?>
        <tr><th colspan="3">Subtotal</th><td><?= e(format_money($o['subtotal_minor'])) ?></td></tr>
        <tr><th colspan="3">Discount</th><td>−<?= e(format_money($o['discount_minor'])) ?></td></tr>
        <tr><th colspan="3">Delivery fee</th><td><?= e(format_money($o['delivery_fee_minor'])) ?></td></tr>
        <tr><th colspan="3">Total</th><td><strong><?= e(format_money($o['total_minor'])) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
  <p>Payment: <?= e($methods[$o['payment_method']]['label'] ?? (string) $o['payment_method']) ?> — <?= e(ucfirst($o['payment_status'])) ?><?= $pay ? ' · Ref: ' . e($pay['payment_reference']) : '' ?>.</p>
  <?php if ($refunds) : ?>
    <p>Refunds:
      <?php foreach ($refunds as $r) : ?>
        <?= e($r['refund_number']) ?> (<?= e(format_money($r['amount_minor'])) ?>, <?= e($r['status']) ?>);
      <?php endforeach; ?>
    </p>
  <?php endif; ?>
  <p class="no-print"><button class="btn primary" onclick="window.print()">Print</button></p>
</div>
<?php if ($cid > 0 && $o['payment_status'] === 'paid') : ?>
<div class="card no-print">
  <h2>Request a refund</h2>
  <form method="post" action="" class="filter-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="refund">
    <input name="amount" inputmode="decimal" placeholder="Amount ₦" required>
    <select name="method"><option value="wallet">Wallet</option><option value="bank">Bank</option><option value="cash">Cash</option></select>
    <input name="reason" maxlength="255" placeholder="Reason" required>
    <button class="btn small primary" type="submit">Request refund</button>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
