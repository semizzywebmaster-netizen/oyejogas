<?php
/**
 * Oyejo Gas - order management desk (Phase 15, AD-09) with delivery
 * visibility (AD-12; full dispatch lands in Phase 16).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('orders.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_edit = has_permission('orders.edit');
$can_cancel = has_permission('orders.cancel');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'status') {
            if (!$can_edit) {
                $errors[] = 'You do not have permission to update orders.';
            } else {
                [$ok, $msg] = adm_order_status((int) ($_POST['order_id'] ?? 0),
                    $_POST['to_status'] ?? '', $_POST['note'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'cancel') {
            if (!$can_cancel) {
                $errors[] = 'You do not have permission to cancel orders.';
            } else {
                [$ok, $msg] = adm_order_cancel((int) ($_POST['order_id'] ?? 0), $_POST['reason'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$statuses = array_keys(adm_order_flow());
$f_status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$rows = adm_orders($f_status, $q);
$view = isset($_GET['view']) ? adm_order_get((int) $_GET['view']) : null;
$next = $view ? adm_order_flow()[$view['status']] : [];

$page_title = 'Orders';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Orders</p>
<h1>Orders</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($view) : ?>
  <div class="card">
    <h2><?= e($view['order_number']) ?> <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $view['status']))) ?></span></h2>
    <p class="result-meta"><?= e($view['customer_name']) ?> (<?= e($view['customer_code']) ?>) · <?= e($view['customer_email']) ?> ·
      Total: ₦<?= number_format((int) $view['total_minor'] / 100, 2) ?> · Payment: <?= e(ucfirst($view['payment_status']) . ($view['payment_method'] ? '/' . $view['payment_method'] : '')) ?> ·
      Placed: <?= e($view['created_at']) ?></p>
    <?php if ($view['address_text']) : ?><p class="result-meta">Deliver to: <?= e($view['address_text']) ?><?= $view['delivery_phone'] ? ' · ' . e($view['delivery_phone']) : '' ?></p><?php endif; ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($view['items'] as $it) : ?>
          <tr><td><?= e($it['name']) ?></td><td><?= number_format((int) $it['qty']) ?></td>
            <td>₦<?= number_format((int) $it['unit_price_minor'] / 100, 2) ?></td>
            <td>₦<?= number_format((int) $it['total_minor'] / 100, 2) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <h2>Delivery</h2>
    <?php if (!$view['delivery']) : ?><p class="result-meta">No delivery record yet (dispatch board lands in Phase 16).</p>
    <?php else : ?>
      <p class="result-meta"><?= e($view['delivery']['delivery_number']) ?> · <?= e(ucfirst(str_replace('_', ' ', $view['delivery']['status']))) ?> ·
        Driver: <?= e((string) ($view['delivery']['driver_code'] ?: 'unassigned')) ?></p>
    <?php endif; ?>
    <h2>Status history</h2>
    <?php if (!$view['history']) : ?><p class="result-meta">No transitions recorded.</p>
    <?php else : ?>
      <ul class="ticks">
        <?php foreach ($view['history'] as $h) : ?>
          <li><?= e(($h['from_status'] ?: '—') . ' → ' . $h['to_status']) ?> · <?= e($h['created_at']) ?> · <?= e((string) ($h['actor_name'] ?: 'system')) ?><?= $h['note'] ? ' — ' . e($h['note']) : '' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($can_edit && $next) : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="order_id" value="<?= (int) $view['id'] ?>">
        <label>Move to
          <select name="to_status">
            <?php foreach ($next as $n) : ?><option value="<?= $n ?>"><?= e(ucfirst(str_replace('_', ' ', $n))) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Note (optional)<input name="note" maxlength="255"></label>
        <p><button class="btn small primary" type="submit">Update status</button></p>
      </form>
    <?php endif; ?>
    <?php if ($can_cancel && in_array($view['status'], ['pending', 'confirmed'], true)) : ?>
      <form method="post" action="" class="stack" onsubmit="return confirm('Cancel this order?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="order_id" value="<?= (int) $view['id'] ?>">
        <label>Cancellation reason<input name="reason" maxlength="255" required></label>
        <p><button class="btn small ghost" type="submit">Cancel order</button></p>
      </form>
    <?php endif; ?>
    <p><a class="btn ghost small" href="<?= e(url('admin/orders.php')) ?>">&larr; All orders</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <form method="get" action="" class="filter-row">
      <input name="q" value="<?= e($q) ?>" placeholder="Order no, name, email…" maxlength="100">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s) : ?>
          <option value="<?= $s ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn small" type="submit">Search</button>
    </form>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Payment</th><th>Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr><td><strong><?= e($r['order_number']) ?></strong></td><td><?= e($r['customer_name']) ?></td>
            <td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
            <td>₦<?= number_format((int) $r['total_minor'] / 100, 2) ?></td><td><?= e(ucfirst($r['payment_status'])) ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td><a href="<?= e(url('admin/orders.php?view=' . (int) $r['id'])) ?>">View</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
