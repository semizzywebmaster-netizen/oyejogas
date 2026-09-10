<?php
/**
 * Oyejo Gas - dispatch board, zones, slots, cash & logistics reports (Phase 16).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
if (!oyejo_feature('delivery_service')) {
    http_response_code(403);
    require BASE_PATH . '/errors/403.php';
    exit;
}
require_permission('deliveries.view');
require_once BASE_PATH . '/includes/delivery.php';

$me = current_user();
$can_assign = has_permission('deliveries.assign');
$can_update = has_permission('deliveries.update');
$can_zones = has_permission('zones.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            if (!$can_assign) {
                $errors[] = 'You do not have permission to dispatch deliveries.';
            } else {
                [$ok, $msg] = del_create($_POST['kind'] ?? 'order', $_POST['ref_number'] ?? '',
                    (int) ($_POST['zone_id'] ?? 0), (int) ($_POST['slot_id'] ?? 0),
                    $_POST['instructions'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'assign') {
            if (!$can_assign) {
                $errors[] = 'You do not have permission to assign deliveries.';
            } else {
                [$ok, $msg] = del_assign((int) ($_POST['delivery_id'] ?? 0),
                    (int) ($_POST['driver_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'status') {
            if (!$can_update) {
                $errors[] = 'You do not have permission to update deliveries.';
            } else {
                [$ok, $msg] = del_set_status((int) ($_POST['delivery_id'] ?? 0),
                    $_POST['to_status'] ?? '', ['user_id' => (int) $me['id'], 'is_staff' => true],
                    ['receiver' => $_POST['receiver'] ?? '', 'proof_note' => $_POST['proof_note'] ?? '',
                        'failed_reason' => $_POST['failed_reason'] ?? '']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'instructions') {
            if (!$can_update) {
                $errors[] = 'You do not have permission to update deliveries.';
            } else {
                [$ok, $msg] = del_instructions((int) ($_POST['delivery_id'] ?? 0),
                    $_POST['text'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'zone_save') {
            if (!$can_zones) {
                $errors[] = 'You do not have permission to manage zones.';
            } else {
                [$ok, $msg] = del_zone_save((int) ($_POST['zone_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'zone_delete') {
            if (!$can_zones) {
                $errors[] = 'You do not have permission to manage zones.';
            } else {
                [$ok, $msg] = del_zone_delete((int) ($_POST['zone_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'slot_save') {
            if (!$can_zones) {
                $errors[] = 'You do not have permission to manage slots.';
            } else {
                [$ok, $msg] = del_slot_save((int) ($_POST['slot_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'slot_delete') {
            if (!$can_zones) {
                $errors[] = 'You do not have permission to manage slots.';
            } else {
                [$ok, $msg] = del_slot_delete((int) ($_POST['slot_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$statuses = array_keys(del_flow());
$f_status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$f_driver = (int) ($_GET['driver_id'] ?? 0);
$rows = del_list($f_status, $f_driver);
$view = isset($_GET['view']) ? del_get((int) $_GET['view']) : null;
$next = $view ? del_flow()[$view['status']] : [];
$drivers = db()->query(
    "SELECT d.`id`, d.`driver_code`, d.`availability`, u.`name` FROM `drivers` d
     JOIN `users` u ON u.`id` = d.`user_id` WHERE d.`status` = 'active' ORDER BY u.`name`"
)->fetchAll();
$zones = del_zones();
$slots = del_slots();
$cash = del_cash_list();
$perf = del_performance();
$reports = del_reports();
$edit_zone = isset($_GET['edit_zone']) ? null : null;
$edit_slot = isset($_GET['edit_slot']) ? null : null;
foreach ($zones as $z) {
    if (isset($_GET['edit_zone']) && (int) $z['id'] === (int) $_GET['edit_zone']) {
        $edit_zone = $z;
    }
}
foreach ($slots as $s) {
    if (isset($_GET['edit_slot']) && (int) $s['id'] === (int) $_GET['edit_slot']) {
        $edit_slot = $s;
    }
}

$page_title = 'Dispatch';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Dispatch</p>
<h1>Dispatch board</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($view) : ?>
  <?php [$kind, $ref, $label] = del_kind($view); ?>
  <div class="card">
    <h2><?= e($view['delivery_number']) ?> <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $view['status']))) ?></span></h2>
    <p class="result-meta"><?= e($label) ?> · <?= e((string) ($view['customer_name'] ?: '—')) ?> ·
      Driver: <?= e((string) ($view['driver_code'] ?: 'unassigned')) ?> ·
      Zone: <?= e((string) ($view['zone_name'] ?: '—')) ?> · Slot: <?= e((string) ($view['slot_name'] ?: '—')) ?></p>
    <?php if ($view['address_text']) : ?><p class="result-meta">Address: <?= e($view['address_text']) ?><?= $view['delivery_phone'] ? ' · ' . e($view['delivery_phone']) : '' ?></p><?php endif; ?>
    <p class="result-meta">Instructions: <?= e((string) ($view['customer_instructions'] ?: '—')) ?></p>
    <?php if ($view['driver_note']) : ?><p class="result-meta">Driver notes: <?= e($view['driver_note']) ?></p><?php endif; ?>
    <?php if ($view['proof_note']) : ?><p class="result-meta">POD: <?= e($view['proof_note']) ?><?= $view['proof_image'] ? ' · <a href="' . e(url('uploads/' . $view['proof_image'])) . '">photo</a>' : '' ?></p><?php endif; ?>
    <?php if ($view['failed_reason']) : ?><p class="result-meta">Failed: <?= e($view['failed_reason']) ?></p><?php endif; ?>
    <p class="result-meta">Cash expected: ₦<?= number_format((int) $view['cash_expected_minor'] / 100, 2) ?> ·
      Collected: ₦<?= number_format((int) $view['cash_collected_minor'] / 100, 2) ?></p>
    <?php if ($can_assign && in_array($view['status'], ['pending', 'assigned'], true)) : ?>
      <form method="post" action="" class="filter-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
        <select name="driver_id">
          <?php foreach ($drivers as $dr) : ?>
            <option value="<?= (int) $dr['id'] ?>"<?= (int) $view['driver_id'] === (int) $dr['id'] ? ' selected' : '' ?>><?= e($dr['name'] . ' (' . $dr['driver_code'] . ', ' . str_replace('_', ' ', $dr['availability']) . ')') ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn small primary" type="submit"><?= $view['status'] === 'assigned' ? 'Reassign' : 'Assign' ?></button>
      </form>
    <?php endif; ?>
    <?php if ($can_update && $next) : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
        <label>Move to
          <select name="to_status">
            <?php foreach ($next as $n) : ?><option value="<?= $n ?>"><?= e(ucfirst(str_replace('_', ' ', $n))) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Receiver (for delivered)<input name="receiver" maxlength="120"></label>
        <label>Handover note (for delivered)<input name="proof_note" maxlength="350"></label>
        <label>Failure reason (for failed)<input name="failed_reason" maxlength="255"></label>
        <p><button class="btn small primary" type="submit">Update status</button></p>
      </form>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="instructions">
        <input type="hidden" name="delivery_id" value="<?= (int) $view['id'] ?>">
        <label>Customer instructions<textarea name="text" rows="2"><?= e((string) ($view['customer_instructions'] ?? '')) ?></textarea></label>
        <p><button class="btn small" type="submit">Save instructions</button></p>
      </form>
    <?php endif; ?>
    <p><a class="btn ghost small" href="<?= e(url('admin/dispatch.php')) ?>">&larr; Board</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <form method="get" action="" class="filter-row">
      <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s) : ?>
          <option value="<?= $s ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="driver_id" onchange="this.form.submit()">
        <option value="0">All drivers</option>
        <?php foreach ($drivers as $dr) : ?>
          <option value="<?= (int) $dr['id'] ?>"<?= $f_driver === (int) $dr['id'] ? ' selected' : '' ?>><?= e($dr['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Delivery</th><th>Reference</th><th>Customer</th><th>Driver</th><th>Zone</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr><td><strong><?= e($r['delivery_number']) ?></strong></td>
            <td><?= e((string) ($r['order_number'] ?: ($r['refill_number'] ?: $r['pickup_number']))) ?></td>
            <td><?= e((string) ($r['customer_name'] ?: '—')) ?></td>
            <td><?= e((string) ($r['driver_code'] ?: '—')) ?></td>
            <td><?= e((string) ($r['zone_name'] ?: '—')) ?></td>
            <td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
            <td><a href="<?= e(url('admin/dispatch.php?view=' . (int) $r['id'])) ?>">View</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <?php if ($can_assign) : ?>
  <div class="card">
    <h2>Queue a delivery</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Kind
        <select name="kind">
          <option value="order">Order (confirmed / preparing)</option>
          <option value="refill">Refill (processing / ready)</option>
          <option value="pickup">Pickup (scheduled)</option>
        </select>
      </label>
      <label>Reference number<input name="ref_number" maxlength="30" placeholder="ORD-… / RFL-… / PCK-…" required></label>
      <label>Zone
        <select name="zone_id">
          <option value="0">— auto / none —</option>
          <?php foreach ($zones as $z) : ?>
            <?php if (!(int) $z['is_active']) {
        continue;
    } ?>
            <option value="<?= (int) $z['id'] ?>"><?= e($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Slot
        <select name="slot_id">
          <option value="0">— auto / none —</option>
          <?php foreach ($slots as $s) : ?>
            <?php if (!(int) $s['is_active']) {
        continue;
    } ?>
            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Instructions (optional)<input name="instructions" maxlength="500"></label>
      <p><button class="btn primary" type="submit">Queue delivery</button></p>
    </form>
  </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <h2>Zones &amp; fees</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Zone</th><th>Fee</th><th>Free above</th><th>Active</th><?php if ($can_zones) : ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($zones as $z) : ?>
        <tr><td><strong><?= e($z['name']) ?></strong></td><td>₦<?= number_format((int) $z['fee_minor'] / 100, 2) ?></td>
          <td><?= $z['free_above_minor'] ? '₦' . number_format((int) $z['free_above_minor'] / 100, 2) : '—' ?></td>
          <td><?= (int) $z['is_active'] ? 'Yes' : 'No' ?></td>
          <?php if ($can_zones) : ?><td>
            <a href="<?= e(url('admin/dispatch.php?edit_zone=' . (int) $z['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this zone?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="zone_delete">
              <input type="hidden" name="zone_id" value="<?= (int) $z['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($can_zones) : ?>
  <h2><?= $edit_zone ? 'Edit zone' : 'Add zone' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="zone_save">
    <input type="hidden" name="zone_id" value="<?= (int) ($edit_zone['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit_zone['name'] ?? '')) ?>" maxlength="100" required></label>
    <label>Description<input name="description" value="<?= e((string) ($edit_zone['description'] ?? '')) ?>" maxlength="255"></label>
    <label>Fee (₦)<input name="fee" inputmode="decimal" value="<?= e((string) (($edit_zone['fee_minor'] ?? 0) / 100)) ?>" required></label>
    <label>Free above (₦, optional)<input name="free_above" inputmode="decimal" value="<?= $edit_zone && $edit_zone['free_above_minor'] ? e((string) ($edit_zone['free_above_minor'] / 100)) : '' ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit_zone['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit_zone || (int) $edit_zone['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit_zone ? 'Save changes' : 'Create zone' ?></button>
    <?php if ($edit_zone) : ?><a class="btn ghost" href="<?= e(url('admin/dispatch.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Time slots</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Slot</th><th>Window</th><th>Active</th><?php if ($can_zones) : ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($slots as $s) : ?>
        <tr><td><strong><?= e($s['name']) ?></strong></td>
          <td><?= e(substr($s['window_start'], 0, 5) . '–' . substr($s['window_end'], 0, 5)) ?></td>
          <td><?= (int) $s['is_active'] ? 'Yes' : 'No' ?></td>
          <?php if ($can_zones) : ?><td>
            <a href="<?= e(url('admin/dispatch.php?edit_slot=' . (int) $s['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this slot?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="slot_delete">
              <input type="hidden" name="slot_id" value="<?= (int) $s['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($can_zones) : ?>
  <h2><?= $edit_slot ? 'Edit slot' : 'Add slot' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="slot_save">
    <input type="hidden" name="slot_id" value="<?= (int) ($edit_slot['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit_slot['name'] ?? '')) ?>" maxlength="100" required></label>
    <label>Window start<input name="window_start" value="<?= e($edit_slot ? substr($edit_slot['window_start'], 0, 5) : '') ?>" placeholder="09:00" required></label>
    <label>Window end<input name="window_end" value="<?= e($edit_slot ? substr($edit_slot['window_end'], 0, 5) : '') ?>" placeholder="12:00" required></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit_slot['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit_slot || (int) $edit_slot['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit_slot ? 'Save changes' : 'Create slot' ?></button>
    <?php if ($edit_slot) : ?><a class="btn ghost" href="<?= e(url('admin/dispatch.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Cash collections</h2>
  <?php if (!$cash) : ?><p class="result-meta">No cash recorded yet.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Date</th><th>Delivery</th><th>Driver</th><th>Amount</th><th>Reconciled</th></tr></thead>
      <tbody>
        <?php foreach ($cash as $c) : ?>
          <tr><td><?= e($c['collected_at']) ?></td><td><?= e($c['delivery_number']) ?></td>
            <td><?= e($c['driver_code']) ?></td><td>₦<?= number_format((int) $c['amount_minor'] / 100, 2) ?></td>
            <td><?= (int) $c['reconciled'] ? 'Yes' : 'No' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Driver performance</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Driver</th><th>Assigned</th><th>En route</th><th>Delivered</th><th>Failed</th><th>Cash</th></tr></thead>
    <tbody>
      <?php foreach ($perf as $p) : ?>
        <tr><td><?= e($p['name'] . ' (' . $p['driver_code'] . ')') ?></td>
          <td><?= number_format((int) $p['assigned']) ?></td><td><?= number_format((int) $p['en_route']) ?></td>
          <td><?= number_format((int) $p['delivered']) ?></td><td><?= number_format((int) $p['failed']) ?></td>
          <td>₦<?= number_format((int) $p['cash_minor'] / 100, 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Logistics reports</h2>
  <p class="result-meta">Cash expected: ₦<?= number_format((int) $reports['cash']['expected'] / 100, 2) ?> ·
    Collected: ₦<?= number_format((int) $reports['cash']['collected'] / 100, 2) ?></p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
      <?php foreach ($reports['by_status'] as $r) : ?>
        <tr><td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td><td><?= number_format((int) $r['n']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Zone</th><th>Deliveries</th></tr></thead>
    <tbody>
      <?php foreach ($reports['by_zone'] as $r) : ?>
        <tr><td><?= e($r['zone']) ?></td><td><?= number_format((int) $r['n']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
