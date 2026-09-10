<?php
/**
 * Oyejo Gas - customer notifications inbox (Phase 10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$list = notify_for_customer($cid, 50);

$page_title = 'Notifications';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Notifications</p>
<h1>Notifications</h1>
<?php if (!$list) : ?>
  <div class="card"><p>No notifications yet. Order updates will appear here.</p></div>
<?php else : ?>
  <?php foreach ($list as $n) : ?>
    <article class="card">
      <p><span class="badge"><?= e($n['channel']) ?></span> <span class="badge"><?= e($n['status']) ?></span>
        <span class="result-meta"><?= e($n['created_at']) ?></span></p>
      <h3><?= e($n['subject'] ?: $n['event']) ?></h3>
      <?php if ($n['body'] !== null && $n['body'] !== '') : ?><p><?= nl2br(e($n['body'])) ?></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
