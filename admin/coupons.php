<?php
/**
 * Oyejo Gas - coupon management desk (Phase 15, AD-14).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('coupons.manage');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            [$ok, $msg] = adm_coupon_save((int) ($_POST['coupon_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'delete') {
            [$ok, $msg] = adm_coupon_delete((int) ($_POST['coupon_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$rows = adm_coupons();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}

$page_title = 'Coupons';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Coupons</p>
<h1>Coupons</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['type']) ?></td>
          <td><?= $r['type'] === 'percent' ? ((int) $r['value'] . '%') : ('₦' . number_format((int) $r['value'] / 100, 2)) ?></td>
          <td><?= number_format((int) $r['used_count']) . ($r['usage_limit'] ? ' / ' . number_format((int) $r['usage_limit']) : '') ?></td>
          <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url('admin/coupons.php?edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this coupon?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="coupon_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2><?= $edit ? 'Edit coupon ' . e($edit['code']) : 'Create coupon' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="coupon_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Code<input name="code" value="<?= e((string) ($edit['code'] ?? '')) ?>" maxlength="40" required></label>
    <label>Name (optional)<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="150"></label>
    <label>Type
      <select name="type">
        <option value="fixed"<?= $edit && $edit['type'] === 'fixed' ? ' selected' : '' ?>>Fixed amount (₦)</option>
        <option value="percent"<?= $edit && $edit['type'] === 'percent' ? ' selected' : '' ?>>Percent (%)</option>
      </select>
    </label>
    <label>Fixed value (₦)<input name="value_fixed" inputmode="decimal" value="<?= $edit && $edit['type'] === 'fixed' ? e((string) ($edit['value'] / 100)) : '0' ?>"></label>
    <label>Percent value (1–100)<input type="number" name="value_percent" value="<?= $edit && $edit['type'] === 'percent' ? (int) $edit['value'] : '10' ?>" min="1" max="100"></label>
    <label>Minimum order (₦)<input name="min_order" inputmode="decimal" value="<?= e((string) (($edit['min_order_minor'] ?? 0) / 100)) ?>"></label>
    <label>Maximum discount (₦, optional)<input name="max_discount" inputmode="decimal" value="<?= $edit && $edit['max_discount_minor'] !== null ? e((string) ($edit['max_discount_minor'] / 100)) : '' ?>"></label>
    <label>Usage limit (optional)<input type="number" name="usage_limit" value="<?= e((string) ($edit['usage_limit'] ?? '')) ?>" min="1" max="1000000"></label>
    <label>Starts at<input name="starts_at" value="<?= e((string) ($edit['starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label>Ends at<input name="ends_at" value="<?= e((string) ($edit['ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create coupon' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/coupons.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
