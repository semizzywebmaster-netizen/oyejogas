<?php
/**
 * Oyejo Gas - error reports + log viewer desk (Phase 25).
 * Viewing needs logs.view; resolving errors and rotating logs need
 * logs.manage. Only whitelisted log files are ever read.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('logs.view');

$me = current_user();
$can_manage = has_permission('logs.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'resolve') {
            if (!$can_manage) {
                $errors[] = 'You do not have permission to resolve errors.';
            } else {
                [$ok, $msg] = err_resolve((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'rotate') {
            if (!$can_manage) {
                $errors[] = 'You do not have permission to rotate logs.';
            } else {
                [$n] = logs_rotate((int) $me['id']);
                $message = "Rotation done: $n file(s) rotated.";
            }
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$tab = (string) ($_GET['tab'] ?? 'errors');
if (!in_array($tab, ['errors', 'files'], true)) {
    $tab = 'errors';
}
$f_status = (string) ($_GET['status'] ?? 'open');
if (!in_array($f_status, ['open', 'resolved', 'all'], true)) {
    $f_status = 'open';
}
$reports = $tab === 'errors' ? err_reports($f_status === 'all' ? '' : $f_status) : [];
$files = $tab === 'files' ? logs_list() : [];
$view = $tab === 'files' ? (string) ($_GET['view'] ?? '') : '';
$tail = [];
if ($view !== '') {
    [$tl, $terr] = log_tail($view, 200);
    if ($tl === false) {
        $errors[] = (string) $terr;
        $view = '';
    } else {
        $tail = $tl;
    }
}
[$max_bytes] = logs_rotate_settings();

$page_title = 'Error logs';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Error logs</p>
<h1>Error logs</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<p>
  <a class="btn<?= $tab === 'errors' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/logs.php?tab=errors')) ?>">Errors</a>
  <a class="btn<?= $tab === 'files' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/logs.php?tab=files')) ?>">Log files</a>
</p>

<?php if ($tab === 'errors') : ?>
  <p class="result-meta">Filter:
    <a href="<?= e(url('admin/logs.php?tab=errors&status=open')) ?>">Open</a> ·
    <a href="<?= e(url('admin/logs.php?tab=errors&status=resolved')) ?>">Resolved</a> ·
    <a href="<?= e(url('admin/logs.php?tab=errors&status=all')) ?>">All</a>
  </p>
  <div class="card"><div class="table-scroll"><table class="data">
    <thead><tr><th>Last seen</th><th>Domain</th><th>Level</th><th>Message</th><th>Where</th><th>Count</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($reports as $r) : ?>
        <tr>
          <td><?= e($r['last_seen']) ?></td>
          <td><?= e($r['domain']) ?></td>
          <td><?= e($r['level']) ?></td>
          <td><?= e(mb_substr((string) $r['message'], 0, 120)) ?></td>
          <td><?= e(trim((string) ($r['file'] ?? '') . ':' . ($r['line'] ?? ''), ':')) ?></td>
          <td><?= (int) $r['occurrences'] ?></td>
          <td><?= e($r['status']) ?><?= $r['status'] === 'resolved' ? ' by ' . e((string) ($r['resolver'] ?? '')) : '' ?></td>
          <td>
            <?php if ($r['status'] === 'open' && $can_manage) : ?>
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small" type="submit">Resolve</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reports) : ?><tr><td colspan="8">No error reports. Quiet is good.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
<?php else : ?>
  <div class="card">
    <h2>Files</h2>
    <p class="result-meta">Rotation at <?= round($max_bytes / 1048576, 1) ?> MB per file.
      Scheduled: <code>0 3 * * * /usr/bin/php <?= e(BASE_PATH) ?>/cron/rotate-logs.php</code></p>
    <?php if ($can_manage) : ?>
      <form method="post" action="" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn small" type="submit" name="action" value="rotate">Rotate now</button>
      </form>
    <?php endif; ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>File</th><th>Size</th><th>Updated</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($files as $f) : ?>
          <tr>
            <td><code><?= e($f['name']) ?></code></td>
            <td><?= $f['bytes'] >= 1048576 ? round($f['bytes'] / 1048576, 2) . ' MB' : round($f['bytes'] / 1024, 1) . ' KB' ?></td>
            <td><?= e(date('Y-m-d H:i:s', $f['mtime'])) ?></td>
            <td><a class="btn small ghost" href="<?= e(url('admin/logs.php?tab=files&view=' . $f['name'])) ?>">Tail</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$files) : ?><tr><td colspan="4">No log files yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <?php if ($view !== '') : ?>
    <div class="card">
      <h2>Last lines: <code><?= e($view) ?></code></h2>
      <pre class="log-tail"><?php foreach ($tail as $line) : ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
