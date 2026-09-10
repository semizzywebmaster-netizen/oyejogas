<?php
/**
 * Oyejo Gas - staff review moderation (Phase 18).
 * Perms: portal.admin + reviews.moderate (ST-11…ST-13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('reviews.moderate');
require_once BASE_PATH . '/includes/support.php';

$me = current_user();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        [$ok, $msg] = rev_moderate((int) ($_POST['review_id'] ?? 0), (string) ($_POST['decision'] ?? ''), (int) $me['id']);
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$f_status = (string) ($_GET['status'] ?? 'pending');
$reviews = rev_list($f_status);
$stats = sup_reports()['reviews'];

$page_title = 'Review moderation';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Reviews</p>
<h1>Review moderation</h1>
<?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>
  <?php foreach ($stats as $s) : ?>
    <?= e(ucfirst($s['status'])) ?>: <strong><?= (int) $s['n'] ?></strong>
    <?php if ($s['status'] === 'approved') : ?>(avg <?= number_format((float) $s['avg'], 1) ?>★)<?php endif; ?>
    &nbsp;
  <?php endforeach; ?>
</p>

<form method="get" action="<?= e(url('admin/reviews.php')) ?>" class="filter-row">
  <label>Status
    <select name="status">
      <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label) : ?>
        <option value="<?= e($k) ?>"<?= $k === $f_status ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p><button class="btn" type="submit">Filter</button></p>
</form>

<?php if (!$reviews) : ?>
  <div class="card"><p>No reviews match.</p></div>
<?php else : ?>
  <?php foreach ($reviews as $r) : ?>
    <article class="card">
      <p><strong><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></strong>
        by <?= e($r['customer_name']) ?>
        <?php if (!empty($r['product_name'])) : ?>
          on product <?= e($r['product_name']) ?>
        <?php elseif (!empty($r['order_number'])) : ?>
          on order <?= e($r['order_number']) ?>
        <?php elseif (!empty($r['delivery_number'])) : ?>
          on delivery <?= e($r['delivery_number']) ?>
        <?php endif; ?>
        <span class="result-meta"><?= e($r['created_at']) ?> · <?= e($r['status']) ?></span></p>
      <?php if (!empty($r['title'])) : ?><p><strong><?= e($r['title']) ?></strong></p><?php endif; ?>
      <?php if (!empty($r['body'])) : ?><p><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
      <?php if ($r['status'] === 'pending') : ?>
        <form method="post" action="<?= e(url('admin/reviews.php?status=' . urlencode($f_status))) ?>" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
          <p>
            <button class="btn primary" type="submit" name="decision" value="approved">Approve</button>
            <button class="btn" type="submit" name="decision" value="rejected">Reject</button>
          </p>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
