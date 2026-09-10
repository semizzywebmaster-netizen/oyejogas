<?php
/**
 * Oyejo Gas - staff pickup desk: schedule, collect, complete (Phase 13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.admin');
require_permission('pickups.view');
require_once BASE_PATH . '/includes/pickups.php';

$me = current_user();
$message = '';
$errors = [];
$view = (int) ($_GET['view'] ?? 0);
$fstatus = (string) ($_GET['status'] ?? '');
$types = pickup_types();

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        require_permission('pickups.manage');
        $action = (string) post('action', '');
        $id = (int) post('pickup_id', 0);
        if ($action === 'schedule') {
            list($ok, $msg) = pickup_schedule($id, post('scheduled_date', ''), post('slot_id', 0), post('driver_id', 0));
            $ok ? $message = $msg : $errors[] = $msg;
            $view = $id;
        } elseif ($action === 'collect') {
            $rows = [];
            $sns = post('serial', []);
            $owns = post('ownership', []);
            $conds = post('condition', []);
            $dirs = post('direction', []);
            if (is_array($sns)) {
                foreach ($sns as $i => $sn) {
                    if (trim((string) $sn) === '') {
                        continue;
                    }
                    $rows[] = [
                        'serial' => $sn,
                        'ownership' => $owns[$i] ?? 'customer',
                        'condition' => $conds[$i] ?? '',
                        'direction' => $dirs[$i] ?? 'collected',
                    ];
                }
            }
            list($ok, $msg) = pickup_collect($id, $rows);
            $ok ? $message = $msg : $errors[] = $msg;
            $view = $id;
        } elseif ($action === 'complete') {
            list($ok, $msg) = pickup_complete($id);
            $ok ? $message = $msg : $errors[] = $msg;
            $view = $id;
        } elseif ($action === 'cancel') {
            list($ok, $msg) = pickup_cancel($id, 0, true);
            $ok ? $message = $msg : $errors[] = $msg;
            $view = $id;
        }
    }
}

$pickup = $view > 0 ? pickup_get($view) : null;
if ($view > 0 && !$pickup) {
    http_response_code(404);
    $page_title = 'Pickup not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Pickup not found</h1></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$list = [];
if (!$pickup) {
    $where = '';
    $args = [];
    if (in_array($fstatus, ['requested', 'scheduled', 'collected', 'completed', 'cancelled'], true)) {
        $where = 'WHERE `p`.`status` = ?';
        $args[] = $fstatus;
    }
    $s = db()->prepare(
        'SELECT `p`.*, `s`.`name` AS `size_name`, `u`.`email` AS `customer_email` FROM `pickups` `p`'
        . ' LEFT JOIN `cylinder_sizes` `s` ON `s`.`id` = `p`.`size_id`'
        . ' JOIN `customers` `c` ON `c`.`id` = `p`.`customer_id`'
        . ' JOIN `users` `u` ON `u`.`id` = `c`.`user_id`'
        . " $where ORDER BY `p`.`id` DESC LIMIT 100"
    );
    $s->execute($args);
    $list = $s->fetchAll();
}

$drivers = [];
$slots = [];
if ($pickup) {
    $drivers = db()->query(
        "SELECT `d`.`id`, `d`.`driver_code`, `u`.`name` FROM `drivers` `d`"
        . " JOIN `users` `u` ON `u`.`id` = `d`.`user_id` WHERE `d`.`status` = 'active' ORDER BY `u`.`name` LIMIT 100"
    )->fetchAll();
    $slots = db()->query('SELECT * FROM `delivery_slots` WHERE `is_active` = 1 ORDER BY `sort_order`')->fetchAll();
}
$can_manage = has_permission('pickups.manage');

$page_title = $pickup ? ('Pickup ' . $pickup['pickup_number']) : 'Pickup desk';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($pickup) : ?>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('admin/pickups.php')) ?>">Pickup desk</a> &rsaquo; <?= e($pickup['pickup_number']) ?></p>
  <h1>Pickup <?= e($pickup['pickup_number']) ?></h1>
  <p><span class="badge"><?= e($types[$pickup['type']] ?? $pickup['type']) ?></span>
    <span class="badge"><?= e(ucfirst($pickup['status'])) ?></span></p>
  <div class="table-scroll">
    <table class="data">
      <tbody>
        <tr><th>Size / qty</th><td><?= e((string) $pickup['size_name']) ?> × <?= (int) $pickup['qty'] ?></td></tr>
        <?php if ((int) $pickup['deposit_minor'] > 0) : ?><tr><th>Deposit</th><td><?= e(format_money($pickup['deposit_minor'])) ?></td></tr><?php endif; ?>
        <tr><th>Address</th><td><?= e((string) $pickup['address_line']) ?>, <?= e((string) $pickup['city']) ?></td></tr>
        <tr><th>Date / slot</th><td><?= e((string) $pickup['scheduled_date']) ?> · <?= e((string) $pickup['slot_name']) ?></td></tr>
        <tr><th>Driver</th><td><?= e($pickup['driver_name'] ?: '—') ?></td></tr>
        <?php if ($pickup['notes']) : ?><tr><th>Notes</th><td><?= nl2br(e($pickup['notes'])) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pickup['cylinders']) : ?>
    <div class="card">
      <h2>Cylinders</h2>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Serial</th><th>Direction</th><th>Condition</th><th>Ownership</th><th>Registry</th></tr></thead>
          <tbody>
            <?php foreach ($pickup['cylinders'] as $c) : ?>
              <tr><td><?= e((string) $c['serial_snapshot']) ?></td><td><?= e(ucfirst($c['direction'])) ?></td>
                <td><?= e((string) ($c['condition_on_collect'] ?: '—')) ?></td><td><?= e(ucfirst((string) ($c['ownership'] ?: '—'))) ?></td>
                <td><?= e((string) ($c['cyl_status'] ?: '—')) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($can_manage && $pickup['status'] === 'requested') : ?>
    <div class="card">
      <h2>Schedule</h2>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="schedule">
        <input type="hidden" name="pickup_id" value="<?= (int) $pickup['id'] ?>">
        <label>Date<input type="date" name="scheduled_date" value="<?= e((string) $pickup['scheduled_date']) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Time slot
          <select name="slot_id">
            <?php foreach ($slots as $sl) : ?>
              <option value="<?= (int) $sl['id'] ?>"<?= (int) $pickup['slot_id'] === (int) $sl['id'] ? ' selected' : '' ?>><?= e($sl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Driver
          <select name="driver_id">
            <?php foreach ($drivers as $d) : ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['name'] . ' (' . $d['driver_code'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <p><button class="btn primary" type="submit">Schedule pickup</button></p>
      </form>
    </div>
  <?php endif; ?>
  <?php if ($can_manage && $pickup['status'] === 'scheduled') : ?>
    <div class="card">
      <h2>Record collection</h2>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="collect">
        <input type="hidden" name="pickup_id" value="<?= (int) $pickup['id'] ?>">
        <div class="table-scroll">
          <table class="data">
            <thead><tr><th>#</th><th>Serial</th><th>Direction</th><th>Ownership</th><th>Condition</th></tr></thead>
            <tbody>
              <?php $rows_n = $pickup['type'] === 'exchange' ? (int) $pickup['qty'] * 2 : (int) $pickup['qty']; ?>
              <?php for ($i = 0; $i < $rows_n; $i++) : ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><input name="serial[<?= $i ?>]" maxlength="60" placeholder="e.g. CYL-001"></td>
                  <td>
                    <select name="direction[<?= $i ?>]">
                      <option value="collected">Collected</option>
                      <?php if ($pickup['type'] === 'exchange') : ?><option value="delivered">Delivered</option><?php endif; ?>
                    </select>
                  </td>
                  <td>
                    <select name="ownership[<?= $i ?>]">
                      <option value="customer">Customer</option>
                      <option value="company">Company</option>
                    </select>
                  </td>
                  <td>
                    <select name="condition[<?= $i ?>]">
                      <option value="good">Good</option>
                      <option value="worn">Worn</option>
                      <option value="damaged">Damaged</option>
                    </select>
                  </td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <p><button class="btn primary" type="submit">Record collection</button></p>
      </form>
    </div>
  <?php endif; ?>
  <?php if ($can_manage && $pickup['status'] === 'collected') : ?>
    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="complete">
      <input type="hidden" name="pickup_id" value="<?= (int) $pickup['id'] ?>">
      <p><button class="btn primary" type="submit">Mark completed</button></p>
    </form>
  <?php endif; ?>
  <?php if ($can_manage && in_array($pickup['status'], ['requested', 'scheduled', 'collected'], true)) : ?>
    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="pickup_id" value="<?= (int) $pickup['id'] ?>">
      <p><button class="btn ghost" type="submit">Cancel pickup</button></p>
    </form>
  <?php endif; ?>
<?php else : ?>
  <p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Pickup desk</p>
  <h1>Pickup desk</h1>
  <form method="get" action="" class="filters">
    <label>Status
      <select name="status">
        <option value="">All</option>
        <?php foreach (['requested', 'scheduled', 'collected', 'completed', 'cancelled'] as $st) : ?><option value="<?= $st ?>"<?= $fstatus === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <p><button class="btn ghost" type="submit">Filter</button></p>
  </form>
  <?php if (!$list) : ?><div class="card"><p>No pickup requests.</p></div>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Pickup</th><th>Customer</th><th>Type</th><th>Size</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($list as $r) : ?>
            <tr>
              <td><?= e($r['pickup_number']) ?></td><td><?= e($r['customer_email']) ?></td>
              <td><?= e($types[$r['type']] ?? $r['type']) ?></td><td><?= e((string) $r['size_name']) ?></td>
              <td><?= e(ucfirst($r['status'])) ?></td>
              <td><a href="<?= e(url('admin/pickups.php?view=' . $r['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
