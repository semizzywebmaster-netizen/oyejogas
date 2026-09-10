<?php
/**
 * Oyejo Gas - inventory & cylinder tracking desk (Phase 14).
 * Perms: inventory.view to see; inventory.adjust for adjustments/additions/
 * deductions; inventory.transfer for transfers; cylinders.manage for status.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('inventory.view');
require_once BASE_PATH . '/includes/inventory.php';

$me = current_user();
$can_adjust = has_permission('inventory.adjust');
$can_transfer = has_permission('inventory.transfer');
$can_cylinders = has_permission('cylinders.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'adjust') {
            if (!$can_adjust) {
                $errors[] = 'You do not have permission to adjust stock.';
            } else {
                [$ok, $msg] = inv_adjust($_POST['product_id'] ?? 0, $_POST['new_qty'] ?? -1,
                    $_POST['reason'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'move') {
            $movement = $_POST['movement'] ?? '';
            $need_transfer = in_array($movement, ['transfer_in', 'transfer_out'], true);
            if (($need_transfer && !$can_transfer) || (!$need_transfer && !$can_adjust)) {
                $errors[] = 'You do not have permission for that movement.';
            } else {
                [$ok, $msg] = inv_move($_POST['product_id'] ?? 0, $movement,
                    $_POST['qty'] ?? 0, $_POST['reason'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'cylinder_status') {
            if (!$can_cylinders) {
                $errors[] = 'You do not have permission to manage cylinders.';
            } else {
                [$ok, $msg] = inv_cylinder_set_status($_POST['cylinder_id'] ?? 0,
                    $_POST['status'] ?? '', $_POST['note'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$counts = inv_cylinder_counts();
$low = inv_low_stock();
$accessories = inv_products('accessory');
$tracked = array_values(array_filter(inv_products(), function ($p) {
    return (int) $p['track_inventory'] === 1;
}));
$statuses = inv_cylinder_statuses();
$sizes = db()->query('SELECT `id`, `name` FROM `cylinder_sizes` ORDER BY `sort_order`')->fetchAll();
$q = trim((string) ($_GET['q'] ?? ''));
$f_status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$f_size = (int) ($_GET['size_id'] ?? 0);
$found = ($q !== '' || $f_status !== '' || $f_size > 0) ? inv_search_cylinders($q, $f_status, $f_size) : [];
$filter_product = (int) ($_GET['product_id'] ?? 0);
$movements = inv_movements($filter_product);
$reports = inv_reports();

$page_title = 'Inventory';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Inventory</p>
<h1>Inventory &amp; cylinder tracking</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="stat-grid">
  <div class="card stat"><span class="stat-num"><?= number_format($counts['full']) ?></span><span class="stat-label">Full cylinders</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($counts['empty']) ?></span><span class="stat-label">Empty cylinders</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($counts['awaiting_refill']) ?></span><span class="stat-label">Awaiting refill</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($counts['damaged']) ?></span><span class="stat-label">Damaged</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($counts['in_transit']) ?></span><span class="stat-label">In transit</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($counts['retired']) ?></span><span class="stat-label">Retired</span></div>
</div>

<div class="card">
  <h2>Low-stock alerts (<?= count($low) ?>)</h2>
  <?php if (!$low) : ?><p class="result-meta">All tracked products are above their alert levels.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>SKU</th><th>Product</th><th>In stock</th><th>Alert at</th></tr></thead>
      <tbody>
        <?php foreach ($low as $l) : ?>
          <tr><td><?= e($l['sku']) ?></td><td><?= e($l['name']) ?></td>
            <td><strong><?= number_format((int) $l['stock_qty']) ?></strong></td>
            <td><?= number_format((int) $l['low_stock_at']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Accessory inventory</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>SKU</th><th>Product</th><th>In stock</th><th>Alert at</th><th>Status</th><?php if ($can_adjust) : ?><th>Set stock</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($accessories as $a) : ?>
        <tr><td><?= e($a['sku']) ?></td><td><?= e($a['name']) ?></td>
          <td><?= number_format((int) $a['stock_qty']) ?></td><td><?= number_format((int) $a['low_stock_at']) ?></td>
          <td><?= ((int) $a['track_inventory'] && (int) $a['stock_qty'] <= (int) $a['low_stock_at']) ? '<strong>Low</strong>' : 'OK' ?></td>
          <?php if ($can_adjust) : ?><td>
            <?php if ((int) $a['track_inventory']) : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" value="<?= (int) $a['id'] ?>">
                <input type="number" name="new_qty" value="<?= (int) $a['stock_qty'] ?>" min="0" max="1000000" required>
                <input name="reason" placeholder="Reason" maxlength="200" required>
                <button class="btn small" type="submit">Set</button>
              </form>
            <?php else : ?><span class="result-meta">Untracked</span><?php endif; ?>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_adjust || $can_transfer) : ?>
<div class="card">
  <h2>Record a stock movement</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="move">
    <label>Product
      <select name="product_id">
        <?php foreach ($tracked as $t) : ?>
          <option value="<?= (int) $t['id'] ?>"><?= e($t['name'] . ' (' . number_format((int) $t['stock_qty']) . ' in stock)') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Movement
      <select name="movement">
        <?php if ($can_adjust) : ?>
          <option value="addition">Addition (stock in)</option>
          <option value="deduction">Deduction (stock out)</option>
        <?php endif; ?>
        <?php if ($can_transfer) : ?>
          <option value="transfer_in">Transfer in</option>
          <option value="transfer_out">Transfer out</option>
        <?php endif; ?>
      </select>
    </label>
    <label>Quantity<input type="number" name="qty" value="1" min="1" max="100000" required></label>
    <label>Reason<input name="reason" maxlength="255" required></label>
    <p><button class="btn primary" type="submit">Record movement</button></p>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Cylinder search</h2>
  <form method="get" action="" class="filter-row">
    <input name="q" value="<?= e($q) ?>" placeholder="Serial number…" maxlength="60">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $s) : ?>
        <option value="<?= $s ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="size_id">
      <option value="0">All sizes</option>
      <?php foreach ($sizes as $z) : ?>
        <option value="<?= (int) $z['id'] ?>"<?= $f_size === (int) $z['id'] ? ' selected' : '' ?>><?= e($z['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn small" type="submit">Search</button>
  </form>
  <?php if ($found) : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Serial</th><th>Size</th><th>Ownership</th><th>Status</th><th>Note</th><?php if ($can_cylinders) : ?><th>Change status</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($found as $c) : ?>
          <tr><td><strong><?= e($c['serial']) ?></strong></td><td><?= e($c['size_name']) ?></td>
            <td><?= e(ucfirst($c['ownership'])) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $c['status']))) ?></td>
            <td><?= e((string) ($c['location_note'] ?: '—')) ?></td>
            <?php if ($can_cylinders) : ?><td>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cylinder_status">
                <input type="hidden" name="cylinder_id" value="<?= (int) $c['id'] ?>">
                <select name="status">
                  <?php foreach ($statuses as $s) : ?>
                    <option value="<?= $s ?>"<?= $c['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                  <?php endforeach; ?>
                </select>
                <input name="note" placeholder="Note" maxlength="200">
                <button class="btn small" type="submit">Save</button>
              </form>
            </td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php elseif ($q !== '' || $f_status !== '' || $f_size > 0) : ?>
    <p class="result-meta">No cylinders match that search.</p>
  <?php else : ?>
    <p class="result-meta">Search by serial, status or size. Serials are unique across the registry.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Movement history</h2>
  <form method="get" action="" class="filter-row">
    <select name="product_id" onchange="this.form.submit()">
      <option value="0">All products</option>
      <?php foreach ($tracked as $t) : ?>
        <option value="<?= (int) $t['id'] ?>"<?= $filter_product === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Date</th><th>Product</th><th>Movement</th><th>Qty</th><th>Reference</th><th>Reason</th><th>By</th></tr></thead>
    <tbody>
      <?php foreach ($movements as $m) : ?>
        <tr><td><?= e($m['created_at']) ?></td><td><?= e((string) ($m['product_name'] ?: '—')) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $m['movement']))) ?></td><td><?= number_format((int) $m['qty']) ?></td>
          <td><?= e(($m['ref_type'] ?: '—') . ($m['ref_id'] ? ' #' . $m['ref_id'] : '')) ?></td>
          <td><?= e((string) ($m['reason'] ?: '—')) ?></td><td><?= e((string) ($m['actor_name'] ?: 'System')) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Inventory reports</h2>
  <p class="result-meta">Stock valuation (tracked products): <strong>₦<?= number_format($reports['valuation_minor'] / 100, 2) ?></strong></p>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Movement (30 days)</th><th>Entries</th><th>Units</th></tr></thead>
    <tbody>
      <?php foreach ($reports['movements'] as $r) : ?>
        <tr><td><?= e(ucfirst(str_replace('_', ' ', $r['movement']))) ?></td>
          <td><?= number_format((int) $r['n']) ?></td><td><?= number_format((int) $r['units']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Purchases</th><th>Count</th><th>Total value</th></tr></thead>
    <tbody>
      <?php foreach ($reports['purchases'] as $r) : ?>
        <tr><td><?= e(ucfirst($r['status'])) ?></td><td><?= number_format((int) $r['n']) ?></td>
          <td>₦<?= number_format((int) $r['total'] / 100, 2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
