<?php
/**
 * Oyejo Gas - customer payments: statuses, transfer receipts, online
 * confirmation, retries and receipts (Phase 17).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/payments.php';

$me = current_user();
$stmt = db()->prepare('SELECT `id` FROM `customers` WHERE `user_id` = ?');
$stmt->execute([(int) $me['id']]);
$cid = (int) $stmt->fetchColumn();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cid > 0) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'proof') {
            [$ok, $msg] = pay_upload_proof((int) ($_POST['payment_id'] ?? 0), $cid, $_FILES['proof'] ?? []);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'online_paid') {
            [$ok, $msg] = pay_online_paid((int) ($_POST['payment_id'] ?? 0), $cid, $_POST['gateway_ref'] ?? '');
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'retry') {
            [$ok, $msg] = pay_retry((int) ($_POST['payment_id'] ?? 0), $cid);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'refund') {
            [$ok, $msg] = ref_request((int) ($_POST['order_id'] ?? 0), $_POST['amount'] ?? 0,
                $_POST['reason'] ?? '', $_POST['method'] ?? 'wallet',
                ['customer_id' => $cid, 'user_id' => (int) $me['id'], 'is_staff' => false]);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$rows = $cid > 0 ? pay_for_customer($cid) : [];
$gw = pay_gateway();
$stmt = db()->prepare("SELECT `key`, `value` FROM `settings` WHERE `group_name` = 'payment'");
$stmt->execute();
$bank = [];
foreach ($stmt->fetchAll() as $r) {
    $bank[$r['key']] = $r['value'];
}

$page_title = 'My payments';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Payments</p>
<h1>My payments</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if (!$rows) : ?>
  <div class="card"><p class="result-meta">No payments yet. They appear here after checkout.</p></div>
<?php else : ?>
  <?php foreach ($rows as $p) : ?>
    <div class="card">
      <h2><?= e($p['payment_reference']) ?> <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span></h2>
      <p class="result-meta"><?= $p['order_number'] ? 'Order ' . e($p['order_number']) . ' · ' : '' ?>
        <?= e(ucfirst($p['method'])) ?> · ₦<?= number_format((int) $p['amount_minor'] / 100, 2) ?> · <?= e($p['created_at']) ?></p>
      <?php if ($p['method'] === 'transfer' && $p['status'] === 'pending') : ?>
        <p class="result-meta">Pay to: <strong><?= e((string) ($bank['bank_name'] ?? '—')) ?></strong> ·
          <?= e((string) ($bank['bank_account_name'] ?? '')) ?> · <?= e((string) ($bank['bank_account_number'] ?? '')) ?>
          <?= !empty($bank['bank_instructions']) ? ' — ' . e($bank['bank_instructions']) : '' ?></p>
        <form method="post" action="" enctype="multipart/form-data" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="proof">
          <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
          <label>Upload transfer receipt (JPG/PNG, ≤ 2 MB)<input type="file" name="proof" accept=".jpg,.jpeg,.png" required></label>
          <p><button class="btn small primary" type="submit">Submit receipt</button></p>
        </form>
      <?php endif; ?>
      <?php if ($p['method'] === 'online' && $p['status'] === 'pending') : ?>
        <p class="result-meta">Gateway: <?= e($gw['name']) ?><?= $gw['configured'] ? '' : ' (not configured yet — staff will confirm manually)' ?>.
          <?= $p['gateway_ref'] ? 'Your ref: ' . e($p['gateway_ref']) : 'Complete the payment, then paste the gateway reference.' ?></p>
        <form method="post" action="" class="filter-row">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="online_paid">
          <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
          <input name="gateway_ref" maxlength="100" placeholder="Gateway reference" value="<?= e((string) ($p['gateway_ref'] ?? '')) ?>">
          <button class="btn small primary" type="submit">I have paid</button>
        </form>
      <?php endif; ?>
      <?php if ($p['status'] === 'failed') : ?>
        <form method="post" action="" class="filter-row">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="retry">
          <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
          <button class="btn small primary" type="submit">Retry payment</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($p['status'], ['verified', 'partially_refunded'], true) && $p['order_id']) : ?>
        <p><a class="btn small ghost" href="<?= e(url('customer/invoice.php?order_id=' . (int) $p['order_id'] . '&receipt=1')) ?>">View receipt</a></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
