<?php
/**
 * Oyejo Gas - printable invoice for an owned order (Phase 10).
 * Rendered on demand from order data; persistent finance invoices arrive in Phase 17.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/cart.php';

$logged = is_logged_in();
$me = $logged ? current_user() : null;
$cid = $logged ? cart_customer_id((int) $me['id']) : 0;
$guest_id = (int) ($_SESSION[OYEJO_LAST_ORDER_KEY] ?? 0);
if ($logged) {
    require_permission('shop.order');
}

$id = (int) ($_GET['id'] ?? 0);
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
$s = db()->prepare('SELECT * FROM `order_items` WHERE `order_id` = ? ORDER BY `id`');
$s->execute([$o['id']]);
$items = $s->fetchAll();
$methods = cart_payment_methods();

$page_title = 'Invoice INV-' . $o['order_number'];
require BASE_PATH . '/includes/header.php';
?>
<p class="no-print"><a href="<?= e(url('customer/orders.php?view=' . $o['id'])) ?>">&larr; Back to order</a></p>
<div class="invoice">
  <h1>Invoice INV-<?= e($o['order_number']) ?></h1>
  <p><strong>Oyejo Gas</strong> — Cooking gas, delivered.<br>
    Issued: <?= e(substr($o['created_at'], 0, 16)) ?> · Order: <?= e($o['order_number']) ?></p>
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
  <p>Payment: <?= e($methods[$o['payment_method']]['label'] ?? $o['payment_method']) ?> — <?= e(ucfirst($o['payment_status'])) ?>.</p>
  <p class="no-print"><button class="btn primary" onclick="window.print()">Print</button></p>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
