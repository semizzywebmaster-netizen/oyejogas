<?php
/**
 * Oyejo Gas - customer notifications inbox + WhatsApp opt-in (Phase 22).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$message = '';

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $message = 'Security token mismatch. Reload and try again.';
    } else {
        [$ok, $message] = notify_wa_set($cid, (string) post('wa', '1') === '1');
    }
}

$wa_on = notify_wa_subscribed($cid);
$list = notify_for_customer($cid, 50);

$page_title = 'Notifications';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Notifications</p>
<h1>Notifications</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

<?php if (oyejo_feature('whatsapp_notifications')) : ?>
<div class="card">
  <h2>WhatsApp updates</h2>
  <p class="result-meta">Order, payment and delivery updates on WhatsApp. Currently <strong><?= $wa_on ? 'on' : 'off' ?></strong>.</p>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="wa" value="<?= $wa_on ? '0' : '1' ?>">
    <p><button class="btn" type="submit">Turn <?= $wa_on ? 'off' : 'on' ?></button></p>
  </form>
</div>
<?php endif; ?>

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
