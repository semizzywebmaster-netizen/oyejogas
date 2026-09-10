<?php
/**
 * Oyejo Gas - customer management desk (Phase 15, AD-01).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('customers.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_edit = has_permission('customers.edit');
$can_suspend = has_permission('customers.suspend');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'notes') {
            if (!$can_edit) {
                $errors[] = 'You do not have permission to edit customers.';
            } else {
                [$ok, $msg] = adm_customer_notes((int) ($_POST['customer_id'] ?? 0), $_POST['notes'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'status') {
            if (!$can_suspend) {
                $errors[] = 'You do not have permission to suspend customers.';
            } else {
                [$ok, $msg] = adm_user_status((int) ($_POST['user_id'] ?? 0), $_POST['status'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$f_status = in_array($_GET['status'] ?? '', ['active', 'pending', 'suspended'], true) ? $_GET['status'] : '';
$rows = adm_customers($q, $f_status);
$view = isset($_GET['view']) ? adm_customer_get((int) $_GET['view']) : null;

$page_title = 'Customers';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Customers</p>
<h1>Customers</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($view) : ?>
  <div class="card">
    <h2><?= e($view['name']) ?> <span class="pill"><?= e(ucfirst($view['status'])) ?></span></h2>
    <p class="result-meta"><?= e($view['customer_code']) ?> · <?= e($view['email']) ?> · <?= e((string) ($view['phone'] ?: 'no phone')) ?></p>
    <p class="result-meta">Referral code: <?= e($view['referral_code']) ?> · Addresses: <?= (int) $view['address_count'] ?> ·
      Paid total: ₦<?= number_format($view['paid_total_minor'] / 100, 2) ?> ·
      Joined: <?= e($view['created_at']) ?> · Last login: <?= e((string) ($view['last_login_at'] ?: 'never')) ?></p>
    <?php if ($can_edit) : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notes">
        <input type="hidden" name="customer_id" value="<?= (int) $view['id'] ?>">
        <label>Staff notes<textarea name="notes" rows="2"><?= e((string) ($view['notes'] ?? '')) ?></textarea></label>
        <p><button class="btn small primary" type="submit">Save notes</button></p>
      </form>
    <?php elseif ($view['notes']) : ?>
      <p><?= e($view['notes']) ?></p>
    <?php endif; ?>
    <?php if ($can_suspend) : ?>
      <form method="post" action="" class="filter-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="user_id" value="<?= (int) $view['user_id'] ?>">
        <?php if ($view['status'] === 'suspended') : ?>
          <button class="btn small primary" type="submit" name="status" value="active">Reactivate</button>
        <?php else : ?>
          <button class="btn small ghost" type="submit" name="status" value="suspended">Suspend</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
    <h2>Orders</h2>
    <?php if (!$view['orders']) : ?><p class="result-meta">No orders yet.</p>
    <?php else : ?>
      <div class="table-scroll"><table class="data">
        <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Payment</th><th>Placed</th></tr></thead>
        <tbody>
          <?php foreach ($view['orders'] as $o) : ?>
            <tr><td><a href="<?= e(url('admin/orders.php?view=' . (int) $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
              <td><?= e(ucfirst(str_replace('_', ' ', $o['status']))) ?></td>
              <td>₦<?= number_format((int) $o['total_minor'] / 100, 2) ?></td>
              <td><?= e(ucfirst($o['payment_status'])) ?></td><td><?= e($o['created_at']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <p><a class="btn ghost small" href="<?= e(url('admin/customers.php')) ?>">&larr; All customers</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <form method="get" action="" class="filter-row">
      <input name="q" value="<?= e($q) ?>" placeholder="Name, email, phone, code…" maxlength="100">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['active', 'pending', 'suspended'] as $s) : ?>
          <option value="<?= $s ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn small" type="submit">Search</button>
    </form>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Status</th><th>Orders</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr><td><?= e($r['customer_code']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td>
            <td><?= e(ucfirst($r['status'])) ?></td><td><?= number_format((int) $r['order_count']) ?></td>
            <td><a href="<?= e(url('admin/customers.php?view=' . (int) $r['id'])) ?>">View</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
