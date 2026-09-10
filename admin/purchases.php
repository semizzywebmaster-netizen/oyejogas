<?php
/**
 * Oyejo Gas - suppliers & purchases desk (Phase 14).
 * Perms: purchases.manage for purchases; suppliers.manage for supplier records.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('purchases.manage');
require_once BASE_PATH . '/includes/inventory.php';

$me = current_user();
$can_suppliers = has_permission('suppliers.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'supplier_save') {
            if (!$can_suppliers) {
                $errors[] = 'You do not have permission to manage suppliers.';
            } else {
                [$ok, $msg] = inv_supplier_save((int) ($_POST['supplier_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'supplier_delete') {
            if (!$can_suppliers) {
                $errors[] = 'You do not have permission to manage suppliers.';
            } else {
                [$ok, $msg] = inv_supplier_delete((int) ($_POST['supplier_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'purchase_create') {
            $items = [];
            $descs = (array) ($_POST['description'] ?? []);
            $qtys = (array) ($_POST['qty'] ?? []);
            $costs = (array) ($_POST['unit_cost'] ?? []);
            $pids = (array) ($_POST['product_id'] ?? []);
            foreach ($descs as $i => $d) {
                $items[] = [
                    'description' => $d,
                    'qty' => $qtys[$i] ?? 0,
                    'unit_cost' => $costs[$i] ?? 0,
                    'product_id' => $pids[$i] ?? 0,
                ];
            }
            [$ok, $msg] = inv_purchase_create($_POST['supplier_id'] ?? 0, $items, $_POST['notes'] ?? '', (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'purchase_receive') {
            [$ok, $msg] = inv_purchase_receive((int) ($_POST['purchase_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'purchase_cancel') {
            [$ok, $msg] = inv_purchase_cancel((int) ($_POST['purchase_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$suppliers = inv_suppliers();
$f_status = in_array($_GET['status'] ?? '', ['ordered', 'received', 'cancelled'], true) ? $_GET['status'] : '';
$purchases = inv_purchases($f_status);
$view = isset($_GET['view']) ? inv_purchase_get((int) $_GET['view']) : null;
$tracked = array_values(array_filter(inv_products(), function ($p) {
    return (int) $p['track_inventory'] === 1;
}));
$edit_supplier = null;
if (isset($_GET['edit_supplier'])) {
    foreach ($suppliers as $s) {
        if ((int) $s['id'] === (int) $_GET['edit_supplier']) {
            $edit_supplier = $s;
        }
    }
}

$page_title = 'Suppliers & purchases';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Purchasing</p>
<h1>Suppliers &amp; purchases</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($view) : ?>
  <div class="card">
    <h2><?= e($view['purchase_number']) ?> <span class="pill"><?= e(ucfirst($view['status'])) ?></span></h2>
    <p class="result-meta">Supplier: <?= e($view['supplier_name']) ?> · Total: ₦<?= number_format((int) $view['total_minor'] / 100, 2) ?> · Ordered: <?= e($view['created_at']) ?><?= $view['received_at'] ? ' · Received: ' . e($view['received_at']) : '' ?></p>
    <?php if ($view['notes']) : ?><p><?= e($view['notes']) ?></p><?php endif; ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Description</th><th>Linked product</th><th>Qty</th><th>Unit cost</th><th>Line total</th></tr></thead>
      <tbody>
        <?php foreach ($view['items'] as $it) : ?>
          <tr><td><?= e($it['description']) ?></td><td><?= e((string) ($it['product_name'] ?: '—')) ?></td>
            <td><?= number_format((int) $it['qty']) ?></td><td>₦<?= number_format((int) $it['unit_cost_minor'] / 100, 2) ?></td>
            <td>₦<?= number_format((int) $it['qty'] * (int) $it['unit_cost_minor'] / 100, 2) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($view['status'] === 'ordered') : ?>
      <form method="post" action="" class="filter-row">
        <?= csrf_field() ?>
        <input type="hidden" name="purchase_id" value="<?= (int) $view['id'] ?>">
        <button class="btn primary small" type="submit" name="action" value="purchase_receive">Receive into stock</button>
        <button class="btn ghost small" type="submit" name="action" value="purchase_cancel">Cancel purchase</button>
      </form>
    <?php endif; ?>
    <p><a class="btn ghost small" href="<?= e(url('admin/purchases.php')) ?>">&larr; All purchases</a></p>
  </div>
<?php else : ?>
  <div class="card">
    <h2>Purchases<?= $f_status !== '' ? ' — ' . e(ucfirst($f_status)) : '' ?></h2>
    <p class="filter-row">
      <a class="btn small<?= $f_status === '' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/purchases.php')) ?>">All</a>
      <a class="btn small<?= $f_status === 'ordered' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/purchases.php?status=ordered')) ?>">Ordered</a>
      <a class="btn small<?= $f_status === 'received' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/purchases.php?status=received')) ?>">Received</a>
      <a class="btn small<?= $f_status === 'cancelled' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/purchases.php?status=cancelled')) ?>">Cancelled</a>
    </p>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Number</th><th>Supplier</th><th>Total</th><th>Status</th><th>Ordered</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($purchases as $p) : ?>
          <tr><td><strong><?= e($p['purchase_number']) ?></strong></td><td><?= e($p['supplier_name']) ?></td>
            <td>₦<?= number_format((int) $p['total_minor'] / 100, 2) ?></td><td><?= e(ucfirst($p['status'])) ?></td>
            <td><?= e($p['created_at']) ?></td>
            <td><a href="<?= e(url('admin/purchases.php?view=' . (int) $p['id'])) ?>">View</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <h2>Record a purchase</h2>
    <?php if (!$suppliers) : ?><p class="result-meta">Add a supplier below first.</p>
    <?php else : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="purchase_create">
        <label>Supplier
          <select name="supplier_id">
            <?php foreach ($suppliers as $s) : ?>
              <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="table-scroll"><table class="data">
          <thead><tr><th>Description</th><th>Linked product</th><th>Qty</th><th>Unit cost (₦)</th></tr></thead>
          <tbody>
            <?php for ($i = 0; $i < 4; $i++) : ?>
              <tr>
                <td><input name="description[]" maxlength="255" placeholder="e.g. 12.5kg cylinders × 20"></td>
                <td><select name="product_id[]">
                  <option value="0">— none —</option>
                  <?php foreach ($tracked as $t) : ?>
                    <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                  <?php endforeach; ?>
                </select></td>
                <td><input type="number" name="qty[]" min="0" max="100000" value="<?= $i === 0 ? '1' : '0' ?>"></td>
                <td><input name="unit_cost[]" inputmode="decimal" value="0" placeholder="0.00"></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table></div>
        <label>Notes (optional)<input name="notes" maxlength="2000"></label>
        <p><button class="btn primary" type="submit">Save purchase</button></p>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Suppliers (<?= count($suppliers) ?>)</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Purchases</th><?php if ($can_suppliers) : ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($suppliers as $s) : ?>
        <tr><td><strong><?= e($s['name']) ?></strong></td><td><?= e((string) ($s['contact_person'] ?: '—')) ?></td>
          <td><?= e((string) ($s['phone'] ?: '—')) ?></td><td><?= e((string) ($s['email'] ?: '—')) ?></td>
          <td><?= number_format((int) $s['purchase_count']) ?></td>
          <?php if ($can_suppliers) : ?><td>
            <a href="<?= e(url('admin/purchases.php?edit_supplier=' . (int) $s['id'])) ?>">Edit</a>
            <?php if ((int) $s['purchase_count'] === 0) : ?>
              <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this supplier?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="supplier_delete">
                <input type="hidden" name="supplier_id" value="<?= (int) $s['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_suppliers) : ?>
<div class="card">
  <h2><?= $edit_supplier ? 'Edit supplier' : 'Add a supplier' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="supplier_save">
    <input type="hidden" name="supplier_id" value="<?= (int) ($edit_supplier['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit_supplier['name'] ?? '')) ?>" maxlength="190" required></label>
    <label>Contact person<input name="contact_person" value="<?= e((string) ($edit_supplier['contact_person'] ?? '')) ?>" maxlength="150"></label>
    <label>Phone<input name="phone" value="<?= e((string) ($edit_supplier['phone'] ?? '')) ?>" maxlength="30"></label>
    <label>Email<input name="email" type="email" value="<?= e((string) ($edit_supplier['email'] ?? '')) ?>" maxlength="190"></label>
    <label>Address<input name="address" value="<?= e((string) ($edit_supplier['address'] ?? '')) ?>" maxlength="255"></label>
    <label>Notes<textarea name="notes" rows="2"><?= e((string) ($edit_supplier['notes'] ?? '')) ?></textarea></label>
    <p><button class="btn primary" type="submit"><?= $edit_supplier ? 'Save changes' : 'Add supplier' ?></button>
    <?php if ($edit_supplier) : ?><a class="btn ghost" href="<?= e(url('admin/purchases.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
