<?php
/**
 * Oyejo Gas - add-on registry desk (Phase 15, AD-19).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('addons.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_manage = has_permission('addons.manage');
$message = '';
$errors = [];
if (!oyejo_feature('addons')) {
    $errors[] = 'The add-on system is currently disabled (Settings → Feature toggles).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$can_manage) {
        $errors[] = 'You do not have permission to manage add-ons.';
    } elseif (!oyejo_feature('addons')) {
        $errors[] = 'The add-on system is disabled. Enable it in Settings first.';
    } else {
        [$ok, $msg] = adm_addon_status($_POST['slug'] ?? '', $_POST['to_status'] ?? '', (int) $me['id']);
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$rows = adm_addons();
$flow = [
    'registered' => ['installed'],
    'installed' => ['enabled', 'disabled'],
    'enabled' => ['disabled'],
    'disabled' => ['enabled'],
];

$page_title = 'Add-ons';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Add-ons</p>
<h1>Add-ons</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <?php if (!$rows) : ?><p class="result-meta">No add-ons registered yet. Drop one into <code>addons/</code> (see <code>addons/README.md</code>).</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Add-on</th><th>Version</th><th>Status</th><th>Installed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $a) : ?>
          <tr><td><strong><?= e($a['name']) ?></strong><br><span class="result-meta"><?= e($a['slug']) ?></span></td>
            <td><?= e($a['version']) ?></td><td><?= e(ucfirst($a['status'])) ?></td>
            <td><?= e((string) ($a['installed_at'] ?: '—')) ?></td>
            <td>
              <?php if ($can_manage) : ?>
                <?php foreach ($flow[$a['status']] ?? [] as $n) : ?>
                  <form method="post" action="" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                    <input type="hidden" name="to_status" value="<?= $n ?>">
                    <button class="btn small<?= $n === 'enabled' ? ' primary' : ' ghost' ?>" type="submit"><?= e(ucfirst($n)) ?></button>
                  </form>
                <?php endforeach; ?>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
