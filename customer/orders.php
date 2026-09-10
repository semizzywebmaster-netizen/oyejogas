<?php
/**
 * Oyejo Gas - customer orders: list, detail/confirmation, cancellation (Phase 9).
 * Full history/tracking UI lands in Phase 10; this is the working core.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/cart.php';

$logged = is_logged_in();
$cu = $logged ? current_user() : null;
$uid = $logged ? (int) ($cu['id'] ?? $cu['user_id'] ?? 0) : 0;
$cid = ($logged && $uid > 0) ? cart_customer_id($uid) : 0;
$guest_id = (int) ($_SESSION[OYEJO_LAST_ORDER_KEY] ?? 0);

$view = (int) ($_GET['view'] ?? 0);
$message = '';
$error = '';

if ($view <= 0 && !$logged) {
    $page_title = 'Login required';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><p class="pill">Orders</p><h1>Login required</h1>'
        . '<p><a class="btn primary" href="' . e(url('customer/login.php')) . '">Log in</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}
if ($logged) {
    require_permission('shop.order');
}

/** Can this visitor see/cancel this order row? Owner, or guest via session. */
function orders_mine(array $o, $cid, $guest_id) {
    if ($cid > 0 && (int) $o['customer_id'] === $cid) {
        return true;
    }
    return $guest_id > 0 && (int) $o['id'] === $guest_id;
}

$order = null;
$items = [];
$history = [];
if ($view > 0) {
    $s = db()->prepare(
        'SELECT `o`.*, `z`.`name` AS `zone_name`, `s`.`name` AS `slot_name` FROM `orders` `o`'
        . ' LEFT JOIN `delivery_zones` `z` ON `z`.`id` = `o`.`zone_id`'
        . ' LEFT JOIN `delivery_slots` `s` ON `s`.`id` = `o`.`slot_id`'
        . ' WHERE `o`.`id` = ? LIMIT 1'
    );
    $s->execute([$view]);
    $order = $s->fetch();
    if (!$order || !orders_mine($order, $cid, $guest_id)) {
        http_response_code(404);
        $page_title = 'Order not found';
        require BASE_PATH . '/includes/header.php';
        echo '<section class="stub"><h1>Order not found</h1><p><a href="' . e(url('customer/orders.php')) . '">Back to orders</a></p></section>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
    $s = db()->prepare('SELECT * FROM `order_items` WHERE `order_id` = ? ORDER BY `id`');
    $s->execute([$order['id']]);
    $items = $s->fetchAll();
    $s = db()->prepare('SELECT * FROM `order_status_history` WHERE `order_id` = ? ORDER BY `id`');
    $s->execute([$order['id']]);
    $history = $s->fetchAll();
}

if (request_method() === 'POST' && $order) {
    if (!csrf_verify(post('csrf_token'))) {
        $error = 'Security token mismatch. Reload and try again.';
    } elseif ((string) post('action', '') === 'cancel' && (int) post('order_id', 0) === (int) $order['id']) {
        list($ok, $msg) = order_cancel($order['id'], $order['customer_id'], $uid, post('reason', ''));
        if ($ok) {
            $message = $msg;
            $s = db()->prepare('SELECT * FROM `orders` WHERE `id` = ? LIMIT 1');
            $s->execute([$order['id']]);
            $order = array_merge($order, $s->fetch());
        } else {
            $error = $msg;
        }
    }
}

$list = [];
if ($view <= 0 && $cid > 0) {
    $s = db()->prepare('SELECT * FROM `orders` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 50');
    $s->execute([$cid]);
    $list = $s->fetchAll();
}

$methods = cart_payment_methods();
$page_title = $order ? ('Order ' . $order['order_number']) : 'My orders';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($order) : ?>
  <?php if (isset($_GET['placed'])) : ?>
    <div class="alert alert-success">
      Thank you! Your order <strong><?= e($order['order_number']) ?></strong> is confirmed.
      <?php if (!$logged) : ?>We created your account — check your email to verify it, then log in to track future orders.<?php endif; ?>
      <?php if ($order['payment_method'] === 'transfer') : ?>Please complete your bank transfer; we will confirm on receipt.<?php endif; ?>
      <?php if ($order['payment_method'] === 'online') : ?>Online payment opens in Phase 17; your order is held as unpaid.<?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error !== '') : ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <p class="crumbs"><a href="<?= e(url('customer/orders.php')) ?>">Orders</a> &rsaquo; <?= e($order['order_number']) ?></p>
  <h1>Order <?= e($order['order_number']) ?></h1>
  <p>
    <span class="badge"><?= e(ucfirst($order['status'])) ?></span>
    <span class="badge"><?= e($methods[$order['payment_method']]['label'] ?? $order['payment_method']) ?></span>
    <span class="badge"><?= e(ucfirst($order['payment_status'])) ?></span>
  </p>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it) : ?>
          <tr>
            <td><?= e($it['name']) ?></td><td><?= (int) $it['qty'] ?></td>
            <td><?= e(format_money($it['unit_price_minor'])) ?></td><td><?= e(format_money($it['total_minor'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-scroll">
    <table class="data">
      <tbody>
        <tr><th>Subtotal</th><td><?= e(format_money($order['subtotal_minor'])) ?></td></tr>
        <tr><th>Discount</th><td>−<?= e(format_money($order['discount_minor'])) ?></td></tr>
        <tr><th>Delivery fee<?= $order['zone_name'] ? ' (' . e($order['zone_name']) . ')' : '' ?></th><td><?= e(format_money($order['delivery_fee_minor'])) ?></td></tr>
        <tr><th>Total</th><td><strong><?= e(format_money($order['total_minor'])) ?></strong></td></tr>
        <tr><th>Deliver to</th><td><?= e((string) $order['address_text']) ?><br><?= e((string) $order['delivery_phone']) ?><?php if ($order['slot_name']) : ?><br>Slot: <?= e($order['slot_name']) ?><?php endif; ?></td></tr>
      </tbody>
    </table>
  </div>
  <?php if (in_array($order['status'], ['pending', 'confirmed'], true)) : ?>
    <div class="card">
      <h2>Cancel this order</h2>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
        <label>Reason (optional)<input name="reason" maxlength="255"></label>
        <p><button class="btn ghost" type="submit">Cancel order</button></p>
      </form>
    </div>
  <?php elseif ($order['status'] === 'cancelled') : ?>
    <p class="result-meta">Cancelled<?= $order['cancelled_reason'] ? ': ' . e($order['cancelled_reason']) : '' ?>.</p>
  <?php endif; ?>
<?php else : ?>
  <div class="page-hero"><p class="pill">Orders</p><h1>My orders</h1></div>
  <?php if (!$list) : ?>
    <div class="card"><p>No orders yet.</p><p><a class="btn primary" href="<?= e(url('shop.php')) ?>">Start shopping</a></p></div>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th>Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($list as $o) : ?>
            <tr>
              <td><?= e($o['order_number']) ?></td>
              <td><?= e(substr($o['created_at'], 0, 16)) ?></td>
              <td><?= e(ucfirst($o['status'])) ?></td>
              <td><?= e(ucfirst($o['payment_status'])) ?></td>
              <td><?= e(format_money($o['total_minor'])) ?></td>
              <td><a href="<?= e(url('customer/orders.php?view=' . $o['id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
