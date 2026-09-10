<?php
/**
 * Oyejo Gas - backups, restore & site health desk (Phase 24).
 * View/run/cleanup need backups.create; download/restore/delete need
 * backups.restore. Restores demand a typed-filename confirmation and
 * always keep a pre-restore snapshot first.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('backups.create');
require_once BASE_PATH . '/includes/ops.php';

$me = current_user();
$can_restore = has_permission('backups.restore');
$message = '';
$errors = [];

// Secure download (auth + permission checked; filename locked to the row).
if (isset($_GET['download'])) {
    if (!$can_restore) {
        http_response_code(403);
        exit('Forbidden');
    }
    ops_backup_download((int) $_GET['download']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'backup_now') {
            [$ok, $msg] = ops_backup_run((int) $me['id'], 'manual');
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'cleanup') {
            [$del, $kept] = ops_cleanup((int) $me['id']);
            $message = "Cleanup done: $del deleted, $kept kept.";
        } elseif ($action === 'delete') {
            if (!$can_restore) {
                $errors[] = 'You do not have permission to delete backups.';
            } else {
                [$ok, $msg] = ops_backup_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'restore') {
            if (!$can_restore) {
                $errors[] = 'You do not have permission to restore backups.';
            } else {
                [$ok, $msg] = ops_restore(
                    (int) ($_POST['item_id'] ?? 0),
                    (int) $me['id'],
                    (string) ($_POST['confirm'] ?? '')
                );
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$tab = (string) ($_GET['tab'] ?? 'health');
if (!in_array($tab, ['health', 'backups'], true)) {
    $tab = 'health';
}
[$healthy, $checks] = ops_health();
$rows = $tab === 'backups' ? ops_backups_list(100) : [];
$bs = ops_backup_settings();

$page_title = 'Backups & health';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Backups &amp; health</p>
<h1>Backups &amp; health</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<p>
  <a class="btn<?= $tab === 'health' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/backups.php?tab=health')) ?>">Health</a>
  <a class="btn<?= $tab === 'backups' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/backups.php?tab=backups')) ?>">Backups</a>
</p>

<?php if ($tab === 'health') : ?>
  <div class="alert <?= $healthy ? 'alert-success' : 'alert-error' ?>">
    <?= $healthy ? 'All ' . count($checks) . ' health checks pass.' : 'Some health checks need attention.' ?>
  </div>
  <div class="card"><div class="table-scroll"><table class="data">
    <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($checks as $c) : ?>
        <tr><td><?= e($c['label']) ?></td><td><?= $c['ok'] ? 'Pass' : 'FAIL' ?></td><td><?= e($c['detail']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
<?php else : ?>
  <div class="card">
    <h2>Run &amp; clean up</h2>
    <p class="result-meta">Retention: <?= (int) $bs['retention_days'] ?> days, always keep newest <?= (int) $bs['keep_min'] ?>.
      Scheduled: <code>0 2 * * * /usr/bin/php <?= e(BASE_PATH) ?>/cron/backup.php</code></p>
    <form method="post" action="" class="inline-form">
      <?= csrf_field() ?>
      <button class="btn small primary" type="submit" name="action" value="backup_now">Run backup now</button>
      <button class="btn small" type="submit" name="action" value="cleanup">Clean up old backups</button>
    </form>
  </div>
  <div class="card"><div class="table-scroll"><table class="data">
    <thead><tr><th>File</th><th>Size</th><th>Tables/rows</th><th>Source</th><th>Status</th><th>Created</th><th>By</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr>
          <td><code><?= e($r['filename']) ?></code></td>
          <td><?= $r['bytes'] ? round($r['bytes'] / 1024) . ' KB' : '—' ?></td>
          <td><?= (int) $r['tables'] ?>/<?= number_format((int) $r['rows']) ?></td>
          <td><?= e($r['source']) ?></td>
          <td><?= e($r['status']) ?><?= $r['error'] ? ' — ' . e(mb_substr($r['error'], 0, 80)) : '' ?></td>
          <td><?= e($r['created_at']) ?></td>
          <td><?= e((string) ($r['actor'] ?? '—')) ?></td>
          <td>
            <?php if ($can_restore && $r['status'] === 'ok') : ?>
              <a class="btn small ghost" href="<?= e(url('admin/backups.php?download=' . (int) $r['id'])) ?>">Download</a>
              <form method="post" action="" class="inline-form" onsubmit="return confirm('Restore <?= e($r['filename']) ?>? The site enters maintenance briefly and a pre-restore snapshot is kept.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="confirm" placeholder="Type filename to confirm" required autocomplete="off" size="22">
                <button class="btn small" type="submit">Restore</button>
              </form>
              <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete <?= e($r['filename']) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            <?php elseif ($can_restore) : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            <?php else : ?>
              <span class="result-meta">no restore permission</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows) : ?><tr><td colspan="8">No backups yet. Run one above or wait for the scheduled job.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
