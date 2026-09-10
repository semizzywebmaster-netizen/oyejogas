<?php
/**
 * Oyejo Gas - staff & manager accounts desk (Phase 15, AD-02/AD-04).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('users.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_create = has_permission('users.create');
$can_edit = has_permission('users.edit');
$can_suspend = has_permission('users.suspend');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $is_new = ((int) ($_POST['user_id'] ?? 0)) === 0;
            if (($is_new && !$can_create) || (!$is_new && !$can_edit)) {
                $errors[] = 'You do not have permission to manage staff.';
            } else {
                [$ok, $msg] = adm_staff_save((int) ($_POST['user_id'] ?? 0), $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'status') {
            if (!$can_suspend) {
                $errors[] = 'You do not have permission to suspend users.';
            } else {
                [$ok, $msg] = adm_user_status((int) ($_POST['user_id'] ?? 0), $_POST['status'] ?? '', (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'resetpw') {
            if (!$can_edit) {
                $errors[] = 'You do not have permission to reset passwords.';
            } else {
                [$ok, $msg] = adm_password_reset((int) ($_POST['user_id'] ?? 0),
                    (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$rows = adm_staff($q);
$roles = adm_roles_simple();
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM `users` WHERE `id` = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$page_title = 'Staff';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Staff</p>
<h1>Staff &amp; managers</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <form method="get" action="" class="filter-row">
    <input name="q" value="<?= e($q) ?>" placeholder="Name or email…" maxlength="100">
    <button class="btn small" type="submit">Search</button>
  </form>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['role_name']) ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td><td><?= e((string) ($r['last_login_at'] ?: 'never')) ?></td>
          <td>
            <?php if ($can_edit) : ?><a href="<?= e(url('admin/staff.php?edit=' . (int) $r['id'])) ?>">Edit</a><?php endif; ?>
            <?php if ($can_suspend && (int) $r['id'] !== (int) $me['id']) : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit" name="status" value="<?= $r['status'] === 'suspended' ? 'active' : 'suspended' ?>"><?= $r['status'] === 'suspended' ? 'Activate' : 'Suspend' ?></button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_create || ($edit && $can_edit)) : ?>
<div class="card">
  <h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Add staff member' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="150" required></label>
    <label>Email<input name="email" type="email" value="<?= e((string) ($edit['email'] ?? '')) ?>" maxlength="190" required></label>
    <label>Phone<input name="phone" value="<?= e((string) ($edit['phone'] ?? '')) ?>" maxlength="30"></label>
    <label>Role
      <select name="role_id">
        <?php foreach ($roles as $r) : ?>
          <?php if ($r['slug'] === 'customer') {
    continue;
} ?>
          <option value="<?= (int) $r['id'] ?>"<?= $edit && (int) $edit['role_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <?php foreach (['active', 'pending', 'suspended'] as $s) : ?>
          <option value="<?= $s ?>"<?= $edit && $edit['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!$edit) : ?>
      <label>Temporary password<input name="password" type="password" autocomplete="new-password" required></label>
      <label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" required></label>
    <?php endif; ?>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create account' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/staff.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
  <?php if ($edit && $can_edit) : ?>
    <h2>Reset password</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resetpw">
      <input type="hidden" name="user_id" value="<?= (int) $edit['id'] ?>">
      <label>New password<input name="password" type="password" autocomplete="new-password" required></label>
      <label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" required></label>
      <p><button class="btn small" type="submit">Reset password</button></p>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
