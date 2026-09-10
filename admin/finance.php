<?php
/**
 * Oyejo Gas - finance desk: payment verification, refunds, invoices,
 * cash reconciliation and finance reports (Phase 17).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('payments.view');
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/includes/wallet.php';

$me = current_user();
$can_verify = has_permission('payments.verify');
$can_refunds = has_permission('refunds.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'verify') {
            if (!$can_verify) {
                $errors[] = 'You do not have permission to verify payments.';
            } else {
                [$ok, $msg] = pay_verify((int) ($_POST['payment_id'] ?? 0),
                    isset($_POST['approve']), (int) $me['id'], $_POST['note'] ?? '');
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'fail') {
            if (!$can_verify) {
                $errors[] = 'You do not have permission to fail payments.';
            } else {
                [$ok, $msg] = pay_mark_failed((int) ($_POST['payment_id'] ?? 0), (int) $me['id'], $_POST['reason'] ?? '');
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'refund_decide') {
            if (!$can_refunds) {
                $errors[] = 'You do not have permission to manage refunds.';
            } else {
                [$ok, $msg] = ref_decide((int) ($_POST['refund_id'] ?? 0), isset($_POST['approve']), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'refund_complete') {
            if (!$can_refunds) {
                $errors[] = 'You do not have permission to manage refunds.';
            } else {
                [$ok, $msg] = ref_complete((int) ($_POST['refund_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'refund_create') {
            if (!$can_refunds) {
                $errors[] = 'You do not have permission to manage refunds.';
            } else {
                [$ok, $msg] = ref_request((int) ($_POST['order_id'] ?? 0), $_POST['amount'] ?? 0,
                    $_POST['reason'] ?? '', $_POST['method'] ?? 'wallet',
                    ['customer_id' => null, 'user_id' => (int) $me['id'], 'is_staff' => true]);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'reconcile') {
            if (!$can_verify) {
                $errors[] = 'You do not have permission to reconcile cash.';
            } else {
                [$ok, $msg] = recon_mark((int) ($_POST['collection_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$f_status = $_GET['status'] ?? '';
$f_method = $_GET['method'] ?? '';
$payments = pay_list($f_status, $f_method);
$view = isset($_GET['view']) ? pay_get((int) $_GET['view']) : null;
$f_ref = $_GET['ref_status'] ?? '';
$refunds = ref_list($f_ref);
$pending_recon = recon_pending();
$reports = pay_reports();
$gw = pay_gateway();
$invoices = db()->query(
    'SELECT i.*, o.`order_number` FROM `invoices` i JOIN `orders` o ON o.`id` = i.`order_id`
     ORDER BY i.`id` DESC LIMIT 50'
)->fetchAll();

$page_title = 'Finance';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Finance</p>
<h1>Finance</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<p class="result-meta">Gateway: <?= e($gw['name']) ?> · <?= $gw['configured'] ? 'configured (secret in environment)' : 'not configured — verify manually' ?></p>

<?php if ($view) : ?>
  <div class="card">
    <h2><?= e($view['payment_reference']) ?> <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $view['status']))) ?></span></h2>
    <p class="result-meta"><?= $view['order_number'] ? 'Order ' . e($view['order_number']) . ' · ' : '' ?>
      <?= e((string) $view['customer_name']) ?> · <?= e(ucfirst($view['method'])) ?> ·
      ₦<?= number_format((int) $view['amount_minor'] / 100, 2) ?> · <?= e($view['created_at']) ?></p>
    <?php if ($view['gateway']) : ?><p class="result-meta">Gateway: <?= e($view['gateway']) ?><?= $view['gateway_ref'] ? ' · Ref: ' . e($view['gateway_ref']) : '' ?></p><?php endif; ?>
    <?php if ($view['proof_image']) : ?><p class="result-meta">Proof: <a href="<?= e(url('uploads/' . $view['proof_image'])) ?>">view receipt</a></p><?php endif; ?>
    <?php if ($can_verify && $view['status'] === 'pending') : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="payment_id" value="<?= (int) $view['id'] ?>">
        <label>Note (for rejection)<input name="note" maxlength="255"></label>
        <p class="filter-row">
          <button class="btn primary small" type="submit" name="action" value="verify" onclick="this.form.approve.value='1'">Approve &amp; mark paid</button>
          <input type="hidden" name="approve" value="">
          <button class="btn ghost small" type="submit" name="action" value="fail" formnovalidate>Mark failed</button>
        </p>
      </form>
      <form method="post" action="" class="filter-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="fail">
        <input type="hidden" name="payment_id" value="<?= (int) $view['id'] ?>">
        <input name="reason" maxlength="255" placeholder="Failure reason">
        <button class="btn ghost small" type="submit">Fail with reason</button>
      </form>
    <?php endif; ?>
    <p><a class="btn ghost small" href="<?= e(url('admin/finance.php')) ?>">&larr; All payments</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <h2>Payments</h2>
    <form method="get" action="" class="filter-row">
      <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'verified', 'failed', 'partially_refunded', 'refunded'] as $s) : ?>
          <option value="<?= $s ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="method" onchange="this.form.submit()">
        <option value="">All methods</option>
        <?php foreach (['wallet', 'cod', 'transfer', 'online'] as $m) : ?>
          <option value="<?= $m ?>"<?= $f_method === $m ? ' selected' : '' ?>><?= e(ucfirst($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Reference</th><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p) : ?>
          <tr><td><strong><?= e($p['payment_reference']) ?></strong></td><td><?= e((string) ($p['order_number'] ?: '—')) ?></td>
            <td><?= e($p['customer_name']) ?></td><td><?= e(ucfirst($p['method'])) ?></td>
            <td>₦<?= number_format((int) $p['amount_minor'] / 100, 2) ?></td>
            <td><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></td>
            <td><a href="<?= e(url('admin/finance.php?view=' . (int) $p['id'])) ?>">View</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Refunds<?= $f_ref !== '' ? ' — ' . e(ucfirst($f_ref)) : '' ?></h2>
  <p class="filter-row">
    <a class="btn small<?= $f_ref === '' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/finance.php')) ?>">All</a>
    <?php foreach (['pending', 'approved', 'completed', 'rejected'] as $s) : ?>
      <a class="btn small<?= $f_ref === $s ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/finance.php?ref_status=' . $s)) ?>"><?= e(ucfirst($s)) ?></a>
    <?php endforeach; ?>
  </p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Refund</th><th>Order</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($refunds as $r) : ?>
        <tr><td><strong><?= e($r['refund_number']) ?></strong></td><td><?= e($r['order_number']) ?></td>
          <td>₦<?= number_format((int) $r['amount_minor'] / 100, 2) ?></td><td><?= e(ucfirst($r['method'])) ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td>
          <td>
            <?php if ($can_refunds && $r['status'] === 'pending') : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refund_decide">
                <input type="hidden" name="refund_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="approve" value="1">
                <button class="btn small primary" type="submit">Approve</button>
              </form>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refund_decide">
                <input type="hidden" name="refund_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit">Reject</button>
              </form>
            <?php endif; ?>
            <?php if ($can_refunds && $r['status'] === 'approved') : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refund_complete">
                <input type="hidden" name="refund_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small primary" type="submit">Complete payout</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($can_refunds) : ?>
  <h2>Raise a refund (staff)</h2>
  <form method="post" action="" class="filter-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="refund_create">
    <input name="order_id" inputmode="numeric" placeholder="Order ID" required>
    <input name="amount" inputmode="decimal" placeholder="Amount ₦" required>
    <select name="method"><option value="wallet">Wallet</option><option value="bank">Bank</option><option value="cash">Cash</option></select>
    <input name="reason" maxlength="255" placeholder="Reason" required>
    <button class="btn small primary" type="submit">Raise</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Invoices (latest 50)</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Invoice</th><th>Order</th><th>Issued</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $i) : ?>
        <tr><td><strong><?= e($i['invoice_number']) ?></strong></td><td><?= e($i['order_number']) ?></td>
          <td><?= e($i['issued_at']) ?></td><td><?= e(ucfirst($i['status'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Cash reconciliation (<?= count($pending_recon) ?> pending)</h2>
  <?php if (!$pending_recon) : ?><p class="result-meta">All driver cash is reconciled.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Date</th><th>Driver</th><th>Delivery</th><th>Amount</th><th>Expected</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pending_recon as $c) : ?>
          <tr><td><?= e($c['collected_at']) ?></td><td><?= e($c['driver_name'] . ' (' . $c['driver_code'] . ')') ?></td>
            <td><?= e($c['delivery_number']) ?></td><td>₦<?= number_format((int) $c['amount_minor'] / 100, 2) ?></td>
            <td>₦<?= number_format((int) $c['cash_expected_minor'] / 100, 2) ?></td>
            <td>
              <?php if ($can_verify) : ?>
                <form method="post" action="" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reconcile">
                  <input type="hidden" name="collection_id" value="<?= (int) $c['id'] ?>">
                  <button class="btn small primary" type="submit">Reconcile</button>
                </form>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Finance reports</h2>
  <p class="result-meta">Cash expected: ₦<?= number_format((int) $reports['cash']['expected'] / 100, 2) ?> ·
    Collected: ₦<?= number_format((int) $reports['cash']['collected'] / 100, 2) ?></p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Method</th><th>Status</th><th>Count</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($reports['by_method'] as $r) : ?>
        <tr><td><?= e(ucfirst($r['method'])) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
          <td><?= number_format((int) $r['n']) ?></td><td>₦<?= number_format((int) $r['total'] / 100, 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Refunds</th><th>Count</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($reports['refunds'] as $r) : ?>
        <tr><td><?= e(ucfirst($r['status'])) ?></td><td><?= number_format((int) $r['n']) ?></td>
          <td>₦<?= number_format((int) $r['total'] / 100, 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Day</th><th>Verified payments</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php foreach ($reports['daily'] as $r) : ?>
        <tr><td><?= e($r['d']) ?></td><td><?= number_format((int) $r['n']) ?></td>
          <td>₦<?= number_format((int) $r['total'] / 100, 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
