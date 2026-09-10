<?php
/**
 * Oyejo Gas - roles & permissions desk (Phase 15, AD-05/AD-06).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('roles.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_manage = has_permission('roles.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$can_manage) {
        $errors[] = 'You do not have permission to manage roles.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            [$ok, $msg] = adm_role_save((int) ($_POST['role_id'] ?? 0), $_POST,
                $_POST['perms'] ?? [], (int) $me['id'], (int) $me['role_id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'delete') {
            [$ok, $msg] = adm_role_delete((int) ($_POST['role_id'] ?? 0), (int) $me['role_id']);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$roles = adm_roles();
$grouped = adm_permissions_grouped();
$edit = null;
$grants = [];
if (isset($_GET['edit'])) {
    foreach ($roles as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
    if ($edit) {
        $grants = adm_role_grants((int) $edit['id']);
    }
}

$page_title = 'Roles';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Roles</p>
<h1>Roles &amp; permissions</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Role</th><th>Slug</th><th>System</th><th>Users</th><th>Permissions</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($roles as $r) : ?>
        <tr><td><strong><?= e($r['name']) ?></strong></td><td><?= e($r['slug']) ?></td>
          <td><?= (int) $r['is_system'] ? 'Yes' : 'No' ?></td>
          <td><?= number_format((int) $r['user_count']) ?></td><td><?= number_format((int) $r['perm_count']) ?></td>
          <td>
            <?php if ($can_manage) : ?>
              <a href="<?= e(url('admin/roles.php?edit=' . (int) $r['id'])) ?>">Edit</a>
              <?php if (!(int) $r['is_system'] && (int) $r['user_count'] === 0) : ?>
                <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this role?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="role_id" value="<?= (int) $r['id'] ?>">
                  <button class="btn small ghost" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($can_manage) : ?>
<div class="card">
  <h2><?= $edit ? 'Edit role ' . e($edit['name']) : 'Create role' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="role_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="100" required></label>
    <label>Slug<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="50" required<?= $edit && (int) $edit['is_system'] ? ' readonly' : '' ?>></label>
    <label>Description<input name="description" value="<?= e((string) ($edit['description'] ?? '')) ?>" maxlength="255"></label>
    <h2>Permissions</h2>
    <?php foreach ($grouped as $group => $perms) : ?>
      <fieldset class="perm-group">
        <legend><?= e(ucfirst($group)) ?></legend>
        <?php foreach ($perms as $p) : ?>
          <label class="check"><input type="checkbox" name="perms[]" value="<?= (int) $p['id'] ?>"<?= in_array((int) $p['id'], $grants, true) ? ' checked' : '' ?>> <?= e($p['name']) ?> <span class="result-meta"><?= e($p['slug']) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create role' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/roles.php')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
