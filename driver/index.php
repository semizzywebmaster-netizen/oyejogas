<?php
/**
 * Oyejo Gas - driver portal: dashboard, availability, deliveries, POD,
 * notes and cash collection (Phase 16, DL-02/DL-03/DL-10…DL-16).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.driver');
if (!oyejo_feature('driver_portal')) {
    http_response_code(403);
    require BASE_PATH . '/errors/403.php';
    exit;
}
require_once BASE_PATH . '/includes/delivery.php';
require_once BASE_PATH . '/includes/payments.php';

$me = current_user();
$profile = del_driver_profile((int) $me['id']);
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $actor = ['user_id' => (int) $me['id'], 'driver_id' => (int) $profile['id'], 'is_staff' => false];
        if ($action === 'availability') {
            [$ok, $msg] = del_availability((int) $profile['id'], $_POST['value'] ?? '', (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
            $profile = del_driver_profile((int) $me['id']);
        } elseif ($action === 'status') {
            [$ok, $msg] = del_set_status((int) ($_POST['delivery_id'] ?? 0), $_POST['to_status'] ?? '', $actor,
                ['receiver' => $_POST['receiver'] ?? '', 'proof_note' => $_POST['proof_note'] ?? '',
                    'failed_reason' => $_POST['failed_reason'] ?? '']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'note') {
            [$ok, $msg] = del_driver_note((int) ($_POST['delivery_id'] ?? 0),
                (int) $profile['id'], $_POST['note'] ?? '');
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'photo') {
            [$ok, $msg] = del_pod_photo((int) ($_POST['delivery_id'] ?? 0),
                (int) $profile['id'], $_FILES['photo'] ?? []);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'cash') {
            [$ok, $msg] = del_collect_cash((int) ($_POST['delivery_id'] ?? 0),
                (int) $profile['id'], $_POST['amount'] ?? 0, $_POST['notes'] ?? '', (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$mine = $profile ? del_list('', (int) $profile['id'], 200) : [];
$open = array_values(array_filter($mine, function ($d) {
    return in_array($d['status'], ['assigned', 'out_for_delivery'], true);
}));
$done = array_values(array_filter($mine, function ($d) {
    return in_array($d['status'], ['delivered', 'failed', 'cancelled'], true);
}));
$view = isset($_GET['view']) ? del_get((int) $_GET['view']) : null;
if ($view && $profile && (int) $view['driver_id'] !== (int) $profile['id']) {
    $view = null;
}
$stats = ['assigned' => 0, 'en_route' => 0, 'delivered' => 0, 'failed' => 0, 'cash' => 0];
foreach ($mine as $m) {
    if ($m['status'] === 'assigned') {
        $stats['assigned']++;
    } elseif ($m['status'] === 'out_for_delivery') {
        $stats['en_route']++;
    } elseif ($m['status'] === 'delivered') {
        $stats['delivered']++;
    } elseif ($m['status'] === 'failed') {
        $stats['failed']++;
    }
    $stats['cash'] += (int) $m['cash_collected_minor'];
}

$page_title = 'Driver portal';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs">Driver</p>
<h1>Driver portal</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if (!$profile) : ?>
  <div class="card"><p class="result-meta">No driver profile is linked to your account yet. Ask dispatch to create one.</p></div>
<?php else : ?>
  <div class="stat-grid">
    <div class="card stat"><span class="stat-num"><?= e($profile['driver_code']) ?></span><span class="stat-label">Driver</span></div>
    <div class="card stat"><span class="stat-num"><?= e(ucfirst(str_replace('_', ' ', $profile['availability']))) ?></span><span class="stat-label">Availability</span></div>
    <div class="card stat"><span class="stat-num"><?= number_format($stats['assigned'] + $stats['en_route']) ?></span><span class="stat-label">Active jobs</span></div>
    <div class="card stat"><span class="stat-num"><?= number_format($stats['delivered']) ?></span><span class="stat-label">Delivered</span></div>
    <div class="card stat"><span class="stat-num">₦<?= number_format($stats['cash'] / 100, 2) ?></span><span class="stat-label">Cash collected</span></div>
  </div>

  <div class="card">
    <h2>Availability</h2>
    <form method="post" action="" class="filter-row">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="availability">
      <?php foreach (['available', 'busy', 'off_duty'] as $a) : ?>
        <button class="btn small<?= $profile['availability'] === $a ? ' primary' : ' ghost' ?>" type="submit" name="value" value="<?= $a ?>"><?= e(ucfirst(str_replace('_', ' ', $a))) ?></button>
      <?php endforeach; ?>
    </form>
  </div>

  <?php if ($view) : ?>
    <?php [$kind, $ref, $label] = del_kind($view); ?>
    <div class="card">
      <h2><?= e($view['delivery_number']) ?> <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $view['status']))) ?></span></h2>
      <p class="result-meta"><?= e($label) ?> · <?= e((string) ($view['customer_name'] ?: '—')) ?> ·
        <?= e((string) ($view['customer_phone'] ?: 'no phone')) ?></p>
      <?php if ($view['address_text']) : ?><p><strong>Address:</strong> <?= e($view['address_text']) ?></p><?php endif; ?>
      <?php if ($view['customer_instructions']) : ?><p><strong>Instructions:</strong> <?= e($view['customer_instructions']) ?></p><?php endif; ?>
      <p class="result-meta">Zone: <?= e((string) ($view['zone_name'] ?: '—')) ?> · Slot: <?= e((string) ($view['slot_name'] ?: '—')) ?> ·
        Cash expected: ₦<?= number_format((int) $view['cash_expected_minor'] / 100, 2) ?> ·
        Collected: ₦<?= number_format((int) $view['cash_collected_minor'] / 100, 2) ?></p>
      <?php if ($view['driver_note']) : ?><p class="result-meta">Notes: <?= e($view['driver_note']) ?></p><?php endif; ?>
      <?php if ($view['proof_note']) : ?><p class="result-meta">POD: <?= e($view['proof_note']) ?></p><?php endif; ?>
      <?php if ($view['failed_reason']) : ?><p class="result-meta">Failed: <?= e($view['failed_reason']) ?></p><?php endif; ?>

      <?php if ($view['status'] === 'assigned') : ?>
        <form method="post" action="" class="filter-row">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <input type="hidden" name="to_status" value="out_for_delivery">
          <button class="btn primary small" type="submit">Start trip</button>
        </form>
      <?php endif; ?>
      <?php if ($view['status'] === 'out_for_delivery') : ?>
        <h2>Complete delivery (proof of delivery)</h2>
        <form method="post" action="" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <input type="hidden" name="to_status" value="delivered">
          <label>Received by<input name="receiver" maxlength="120" required></label>
          <label>Handover note<input name="proof_note" maxlength="350" required></label>
          <p><button class="btn primary" type="submit">Mark delivered</button></p>
        </form>
        <h2>Fail delivery</h2>
        <form method="post" action="" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <input type="hidden" name="to_status" value="failed">
          <label>Reason<input name="failed_reason" maxlength="255" required></label>
          <p><button class="btn small ghost" type="submit">Mark failed</button></p>
        </form>
      <?php endif; ?>
      <?php if (in_array($view['status'], ['assigned', 'out_for_delivery'], true)) : ?>
        <h2>Add a note</h2>
        <form method="post" action="" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="note">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <label>Note<input name="note" maxlength="400" required></label>
          <p><button class="btn small" type="submit">Save note</button></p>
        </form>
      <?php endif; ?>
      <?php if (in_array($view['status'], ['out_for_delivery', 'delivered'], true)) : ?>
        <h2>Attach POD photo</h2>
        <form method="post" action="" enctype="multipart/form-data" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="photo">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <label>Photo (JPG/PNG, ≤ 2 MB)<input type="file" name="photo" accept=".jpg,.jpeg,.png" required></label>
          <p><button class="btn small" type="submit">Upload photo</button></p>
        </form>
        <h2>Record cash</h2>
        <form method="post" action="" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cash">
          <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
          <label>Amount (₦)<input name="amount" inputmode="decimal" required></label>
          <label>Notes (optional)<input name="notes" maxlength="255"></label>
          <p><button class="btn small" type="submit">Record cash</button></p>
        </form>
      <?php endif; ?>
      <p><a class="btn ghost small" href="<?= e(url('driver/')) ?>">&larr; My jobs</a></p>
    </div>
  <?php else : ?>
    <div class="card">
      <h2>My jobs (<?= count($open) ?> active)</h2>
      <?php if (!$open) : ?><p class="result-meta">No active jobs. New assignments appear here.</p>
      <?php else : ?>
        <div class="table-scroll"><table class="data">
          <thead><tr><th>Delivery</th><th>Reference</th><th>Customer</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($open as $o) : ?>
              <tr><td><strong><?= e($o['delivery_number']) ?></strong></td>
                <td><?= e((string) ($o['order_number'] ?: ($o['refill_number'] ?: $o['pickup_number']))) ?></td>
                <td><?= e((string) ($o['customer_name'] ?: '—')) ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $o['status']))) ?></td>
                <td><a href="<?= e(url('driver/?view=' . (int) $o['id'])) ?>">Open</a></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>History (<?= count($done) ?>)</h2>
      <?php if (!$done) : ?><p class="result-meta">Nothing completed yet.</p>
      <?php else : ?>
        <div class="table-scroll"><table class="data">
          <thead><tr><th>Delivery</th><th>Reference</th><th>Status</th><th>Cash</th><th></th></tr></thead>
          <tbody>
            <?php foreach (array_slice($done, 0, 20) as $d) : ?>
              <tr><td><?= e($d['delivery_number']) ?></td>
                <td><?= e((string) ($d['order_number'] ?: ($d['refill_number'] ?: $d['pickup_number']))) ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $d['status']))) ?></td>
                <td>₦<?= number_format((int) $d['cash_collected_minor'] / 100, 2) ?></td>
                <td><a href="<?= e(url('driver/?view=' . (int) $d['id'])) ?>">Open</a></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
