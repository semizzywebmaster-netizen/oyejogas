<?php
/**
 * Oyejo Gas - newsletter subscriber desk (Phase 19, MK-10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('marketing.newsletter');
require_once BASE_PATH . '/includes/marketing.php';

$me = current_user();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        [$ok, $msg] = mk_subscriber_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$f_status = (string) ($_GET['status'] ?? '');
$rows = mk_subscribers($f_status);
$subscribed = 0;
$unsubscribed = 0;
foreach (mk_subscribers() as $r) {
    $r['status'] === 'subscribed' ? $subscribed++ : $unsubscribed++;
}

$page_title = 'Newsletter';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Newsletter</p>
<h1>Newsletter subscribers</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>Subscribed: <strong><?= $subscribed ?></strong> &nbsp; Unsubscribed: <strong><?= $unsubscribed ?></strong></p>

<form method="get" action="<?= e(url('admin/newsletter.php')) ?>" class="filter-row">
  <label>Status
    <select name="status">
      <option value="">All</option>
      <option value="subscribed"<?= $f_status === 'subscribed' ? ' selected' : '' ?>>Subscribed</option>
      <option value="unsubscribed"<?= $f_status === 'unsubscribed' ? ' selected' : '' ?>>Unsubscribed</option>
    </select>
  </label>
  <p><button class="btn" type="submit">Filter</button></p>
</form>

<div class="card">
  <?php if (!$rows) : ?><p>No subscribers match.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Since</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr><td><strong><?= e($r['email']) ?></strong></td><td><?= e((string) ($r['name'] ?? '')) ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td><td><?= e(substr($r['created_at'], 0, 16)) ?></td>
          <td>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Remove this subscriber?');">
              <?= csrf_field() ?>
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Remove</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
