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

<?php if (oyejo_feature('push_notifications')) : ?>
<div class="card">
  <h2>Push notifications</h2>
  <p class="result-meta">Get order and delivery alerts on this device, even when the site is closed.</p>
  <p class="push-row">
    <button class="btn small primary" type="button" id="pushEnable" hidden>Enable push on this device</button>
    <button class="btn small ghost" type="button" id="pushDisable" hidden>Disable push on this device</button>
    <span class="result-meta" id="pushState">Checking…</span>
  </p>
</div>
<script>
(function () {
  if (!window.OyejoPush || !window.OyejoPush.supported()) {
    document.getElementById('pushState').textContent = 'Push is not supported by this browser.';
    return;
  }
  var on = document.getElementById('pushEnable');
  var off = document.getElementById('pushDisable');
  var st = document.getElementById('pushState');
  var saveUrl = <?= json_encode(url('api/push-subscribe.php')) ?>;
  var vapid = <?= json_encode((string) env('VAPID_PUBLIC_KEY', '')) ?>;
  function refresh() {
    navigator.serviceWorker.ready.then(function (reg) {
      return reg.pushManager.getSubscription();
    }).then(function (sub) {
      on.hidden = !!sub;
      off.hidden = !sub;
      st.textContent = sub ? 'Enabled on this device.' : 'Not enabled on this device.';
    });
  }
  on.addEventListener('click', function () {
    if (vapid === '') {
      st.textContent = 'Push is not configured yet. Please try again later.';
      return;
    }
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') {
        st.textContent = 'Permission denied in the browser.';
        return;
      }
      st.textContent = 'Enabling…';
      window.OyejoPush.subscribe(vapid, saveUrl).then(function (r) {
        st.textContent = r && r.message ? r.message : 'Done.';
        refresh();
      }).catch(function () {
        st.textContent = 'Could not enable push. Please try again.';
      });
    });
  });
  off.addEventListener('click', function () {
    st.textContent = 'Disabling…';
    window.OyejoPush.unsubscribeAll(saveUrl).then(function () {
      refresh();
    }).catch(function () {
      st.textContent = 'Could not disable push. Please try again.';
    });
  });
  refresh();
})();
</script>
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
