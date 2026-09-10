<?php
/**
 * Oyejo Gas - staff refill desk: assignment, processing, completion (Phase 12).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.admin');
require_permission('refills.view');
require_once BASE_PATH . '/includes/refills.php';

$me = current_user();
$message = '';
$errors = [];
$view = (int) ($_GET['view'] ?? 0);
$fstatus = (string) ($_GET['status'] ?? '');

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        require_permission('refills.manage');
        $action = (string) post('action', '');
        $id = (int) post('refill_id', 0);
        if ($action === 'advance') {
            list($ok, $msg) = refill_set_status($id, (string) post('to', ''), post('driver_id', 0));
            $ok ? $message = $msg : $errors[] = $msg;
            $view = $id;
        }
    }
}

$refill = $view > 0 ? refill_get($view) : null;
if ($view > 0 && !$refill) {
    http_response_code(404);
    $page_title = 'Refill not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Refill not found</h1></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$list = [];
if (!$refill) {
    $where = '';
    $args = [];
    if (in_array($fstatus, refill_statuses(), true)) {
        $where = 'WHERE `r`.`status` = ?';
        $args[] = $fstatus;
    }
    $s = db()->prepare(
        'SELECT `r`.*, `s`.`name` AS `size_name`, `u`.`email` AS `customer_email` FROM `refills` `r`'
        . ' JOIN `cylinder_sizes` `s` ON `s`.`id` = `r`.`size_id`'
        . ' JOIN `customers` `c` ON `c`.`id` = `r`.`customer_id`'
        . ' JOIN `users` `u` ON `u`.`id` = `c`.`user_id`'
        . " $where ORDER BY `r`.`id` DESC LIMIT 100"
    );
    $s->execute($args);
    $list = $s->fetchAll();
}

$drivers = [];
$next = [];
if ($refill) {
    $drivers = db()->query(
        "SELECT `d`.`id`, `d`.`driver_code`, `u`.`name` FROM `drivers` `d`"
        . " JOIN `users` `u` ON `u`.`id` = `d`.`user_id` WHERE `d`.`status` = 'active' ORDER BY `u`.`name` LIMIT 100"
    )->fetchAll();
    $next = refill_flow()[$refill['status']] ?? [];
}
$can_manage = has_permission('refills.manage');

$page_title = $refill ? ('Refill ' . $refill['refill_number']) : 'Refill desk';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($refill) : ?>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('admin/refills.php')) ?>">Refill desk</a> &rsaquo; <?= e($refill['refill_number']) ?></p>
  <h1>Refill <?= e($refill['refill_number']) ?></h1>
  <p><span class="badge"><?= e(ucfirst($refill['status'])) ?></span>
    <span class="badge"><?= e(ucfirst($refill['fulfillment'])) ?></span></p>
  <div class="table-scroll">
    <table class="data">
      <tbody>
        <tr><th>Size</th><td><?= e($refill['size_name']) ?> × <?= (int) $refill['qty'] ?></td></tr>
        <tr><th>Price</th><td><?= e(format_money($refill['price_minor'])) ?></td></tr>
        <?php if ($refill['customer_cylinder_details']) : ?><tr><th>Cylinder</th><td><?= e($refill['customer_cylinder_details']) ?></td></tr><?php endif; ?>
        <tr><th>Driver</th><td><?= e($refill['driver_name'] ?: '—') ?></td></tr>
        <tr><th>Requested</th><td><?= e($refill['created_at']) ?></td></tr>
        <?php if ($refill['completed_at']) : ?><tr><th>Completed</th><td><?= e($refill['completed_at']) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($can_manage && $next) : ?>
    <div class="card">
      <h2>Move to…</h2>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="advance">
        <input type="hidden" name="refill_id" value="<?= (int) $refill['id'] ?>">
        <label>Next status
          <select name="to">
            <?php foreach ($next as $n) : ?><option value="<?= $n ?>"><?= e(ucfirst($n)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Driver (required for assignment)
          <select name="driver_id">
            <option value="0">— None —</option>
            <?php foreach ($drivers as $d) : ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['name'] . ' (' . $d['driver_code'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <p><button class="btn primary" type="submit">Apply</button></p>
      </form>
    </div>
  <?php endif; ?>
<?php else : ?>
  <p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Refill desk</p>
  <h1>Refill desk</h1>
  <form method="get" action="" class="filters">
    <label>Status
      <select name="status">
        <option value="">All</option>
        <?php foreach (refill_statuses() as $st) : ?><option value="<?= $st ?>"<?= $fstatus === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <p><button class="btn ghost" type="submit">Filter</button></p>
  </form>
  <?php if (!$list) : ?><div class="card"><p>No refill requests.</p></div><?php endif; ?>
  <?php if ($list) : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Refill</th><th>Customer</th><th>Size</th><th>Qty</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($list as $r) : ?>
            <tr>
              <td><?= e($r['refill_number']) ?></td><td><?= e($r['customer_email']) ?></td>
              <td><?= e($r['size_name']) ?></td><td><?= (int) $r['qty'] ?></td>
              <td><?= e(format_money($r['price_minor'])) ?></td><td><?= e(ucfirst($r['status'])) ?></td>
              <td><a href="<?= e(url('admin/refills.php?view=' . $r['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
