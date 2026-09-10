<?php
/**
 * Oyejo Gas - add-on management desk (Phase 26, AO-01…AO-12).
 * Scan/register, dependency checks, install, enable/disable, update,
 * migrations and activity log. Add-on settings live under Settings.
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
        $action = $_POST['action'] ?? '';
        $slug = (string) ($_POST['slug'] ?? '');
        if ($action === 'scan') {
            $r = addon_sync((int) $me['id']);
            $message = 'Scan done: ' . $r['registered'] . ' registered, ' . $r['updated'] . ' updated.';
            foreach ($r['errors'] as $s => $e) {
                $errors[] = $s . ': ' . $e;
            }
        } elseif ($action === 'install') {
            [$ok, $msg] = addon_install($slug, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'enable' || $action === 'disable') {
            [$ok, $msg] = addon_set_status($slug, $action === 'enable' ? 'enabled' : 'disabled', (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'update') {
            [$ok, $msg] = addon_update($slug, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$rows = addon_list();
$view = (string) ($_GET['view'] ?? '');
$detail = $view !== '' ? addon_get($view) : null;
if ($view !== '' && $detail === null) {
    $errors[] = 'Add-on not found.';
    $view = '';
}

$page_title = 'Add-ons';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Add-ons</p>
<h1>Add-ons</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($view === '') : ?>
<div class="card">
  <?php if ($can_manage && oyejo_feature('addons')) : ?>
    <form method="post" action="" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="scan">
      <button class="btn small primary" type="submit">Scan addons/ for new &amp; updated add-ons</button>
    </form>
  <?php endif; ?>
  <?php if (!$rows) : ?><p class="result-meta">No add-ons registered yet. Drop one into <code>addons/</code> (see <code>docs/ADDON-DEVELOPMENT.md</code>), then scan.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Add-on</th><th>Versions</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $a) : ?>
          <tr><td><strong><a href="<?= e(url('admin/addons.php?view=' . $a['slug'])) ?>"><?= e($a['name']) ?></a></strong>
              <br><span class="result-meta"><?= e($a['slug']) ?></span>
              <?php if (!$a['on_disk']) : ?><br><span class="result-meta">Files missing — reinstall or remove the folder contents.</span><?php endif; ?></td>
            <td><span class="result-meta">manifest</span> <?= e($a['version']) ?><br>
              <span class="result-meta">installed</span> <?= e((string) ($a['installed_version'] ?: '—')) ?>
              <?php if ($a['update_available']) : ?><br><strong>Update available</strong><?php endif; ?>
              <?php if ($a['pending_migrations']) : ?><br><span class="result-meta"><?= (int) $a['pending_migrations'] ?> pending migration(s)</span><?php endif; ?></td>
            <td><?= e(ucfirst($a['status'])) ?></td>
            <td>
              <?php if ($can_manage && oyejo_feature('addons')) : ?>
                <?php if ($a['status'] === 'registered') : ?>
                  <form method="post" action="" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="install">
                    <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                    <button class="btn small primary" type="submit">Install</button>
                  </form>
                <?php endif; ?>
                <?php if (in_array($a['status'], ['installed', 'disabled'], true)) : ?>
                  <form method="post" action="" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="enable">
                    <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                    <button class="btn small primary" type="submit">Enable</button>
                  </form>
                <?php endif; ?>
                <?php if ($a['status'] === 'enabled') : ?>
                  <form method="post" action="" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable">
                    <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                    <button class="btn small ghost" type="submit">Disable</button>
                  </form>
                <?php endif; ?>
                <?php if ($a['update_available'] || $a['pending_migrations']) : ?>
                  <form method="post" action="" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                    <button class="btn small ghost" type="submit">Update</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php else : ?>
  <?php
  [$manifest] = addon_read_manifest($detail['slug']);
  [$dep_ok, $dep_issues] = addon_check($detail['slug']);
  [$applied, $pending] = addon_migrations_status($detail['slug']);
  $logs = addon_logs((int) $detail['id']);
  ?>
  <p><a class="btn small ghost" href="<?= e(url('admin/addons.php')) ?>">&larr; All add-ons</a></p>
  <div class="card">
    <h2><?= e($detail['name']) ?> <span class="result-meta"><?= e($detail['slug']) ?> · <?= e(ucfirst($detail['status'])) ?></span></h2>
    <?php if ($manifest !== null && trim((string) ($manifest['description'] ?? '')) !== '') : ?>
      <p><?= e($manifest['description']) ?></p>
    <?php endif; ?>
    <p class="result-meta">Manifest v<?= e($detail['version']) ?> · Installed v<?= e((string) ($detail['installed_version'] ?: '—')) ?> ·
      Installed <?= e((string) ($detail['installed_at'] ?: '—')) ?></p>
    <h3>Dependency check</h3>
    <?php if ($dep_ok) : ?><p>All dependencies are met.</p>
    <?php else : ?><ul><?php foreach ($dep_issues as $i) : ?><li><?= e($i) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <h3>Migrations</h3>
    <?php if (!$applied && !$pending) : ?><p class="result-meta">This add-on ships no migrations.</p>
    <?php else : ?><ul>
      <?php foreach ($applied as $f) : ?><li><?= e($f) ?> — applied</li><?php endforeach; ?>
      <?php foreach ($pending as $f) : ?><li><?= e($f) ?> — pending</li><?php endforeach; ?>
    </ul><?php endif; ?>
    <?php if ($manifest !== null && ($manifest['settings'] ?? []) !== []) : ?>
      <p><a class="btn small ghost" href="<?= e(url('admin/settings.php')) ?>">Edit <?= e($detail['name']) ?> settings</a></p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Activity log</h2>
    <?php if (!$logs) : ?><p class="result-meta">No activity yet.</p>
    <?php else : ?>
      <div class="table-scroll"><table class="data">
        <thead><tr><th>When</th><th>Action</th><th>Detail</th><th>By</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $l) : ?>
            <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['action']) ?></td>
              <td><?= e((string) ($l['detail'] ?: '—')) ?></td><td><?= e((string) ($l['actor'] ?: 'system')) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
