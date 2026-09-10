<?php
/**
 * Oyejo Gas - driver management desk (Phase 15, AD-03).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('drivers.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_create = has_permission('drivers.create');
$can_edit = has_permission('drivers.edit');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $is_new = ((int) ($_POST['driver_id'] ?? 0)) === 0;
        if (($is_new && !$can_create) || (!$is_new && !$can_edit)) {
            $errors[] = 'You do not have permission to manage drivers.';
        } else {
            [$ok, $msg] = adm_driver_save((int) ($_POST['driver_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$rows = adm_drivers();
$users = adm_driver_users();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}

$page_title = 'Drivers';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Drivers</p>
<h1>Drivers</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Vehicle</th><th>Availability</th><th>Status</th><th>Deliveries</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><strong><?= e($r['driver_code']) ?></strong></td><td><?= e($r['name']) ?></td>
          <td><?= e((string) ($r['phone'] ?: '—')) ?></td><td><?= e((string) ($r['vehicle_info'] ?: '—')) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['availability']))) ?></td><td><?= e(ucfirst($r['status'])) ?></td>
          <td><?= number_format((int) $r['delivery_count']) ?></td>
          <td><?php if ($can_edit) : ?><a href="<?= e(url('admin/drivers.php?edit=' . (int) $r['id'])) ?>">Edit</a><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_create || ($edit && $can_edit)) : ?>
<div class="card">
  <h2><?= $edit ? 'Edit driver ' . e($edit['driver_code']) : 'Add driver profile' ?></h2>
  <?php if (!$edit && !$users) : ?>
    <p class="result-meta">No free driver-role users. Create one under <a href="<?= e(url('admin/staff.php')) ?>">Staff</a> first.</p>
  <?php else : ?>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="driver_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <?php if (!$edit) : ?>
        <label>Driver user
          <select name="user_id">
            <?php foreach ($users as $u) : ?>
              <option value="<?= (int) $u['id'] ?>"><?= e($u['name'] . ' (' . $u['email'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <label>Driver code<input name="driver_code" value="<?= e((string) ($edit['driver_code'] ?? '')) ?>" maxlength="20" required></label>
      <label>Vehicle info<input name="vehicle_info" value="<?= e((string) ($edit['vehicle_info'] ?? '')) ?>" maxlength="255"></label>
      <label>Licence no<input name="license_no" value="<?= e((string) ($edit['license_no'] ?? '')) ?>" maxlength="100"></label>
      <label>Availability
        <select name="availability">
          <?php foreach (['available', 'busy', 'off_duty'] as $a) : ?>
            <option value="<?= $a ?>"<?= $edit && $edit['availability'] === $a ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $a))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <?php foreach (['active', 'suspended'] as $s) : ?>
            <option value="<?= $s ?>"<?= $edit && $edit['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create driver' ?></button>
      <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/drivers.php')) ?>">Cancel</a><?php endif; ?></p>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
