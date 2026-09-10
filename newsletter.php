<?php
/**
 * Oyejo Gas - newsletter subscribe / unsubscribe (Phase 19, MK-10).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/marketing.php';

$message = '';
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } elseif (trim((string) post('company', '')) !== '') {
        $message = 'Subscribed. Watch your inbox for offers.'; // Honeypot: pretend success.
    } else {
        $action = (string) post('action', 'subscribe');
        if ($action === 'unsubscribe') {
            [$ok, $msg] = mk_unsubscribe((string) post('email', ''));
        } else {
            [$ok, $msg] = mk_subscribe((string) post('email', ''), (string) post('name', ''));
        }
        $ok ? $message = $msg : $errors[] = $msg;
    }
}

$page_title = 'Newsletter';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">Offers &amp; updates</p>
  <h1>Newsletter</h1>
</section>
<section class="stub" style="max-width:640px">
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <div class="card">
    <h2>Subscribe</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <label style="display:none">Company (leave blank)<input name="company" value="" autocomplete="off"></label>
      <input type="hidden" name="action" value="subscribe">
      <label>Name (optional)<input name="name" maxlength="150"></label>
      <label>Email<input type="email" name="email" required maxlength="190"></label>
      <p><button class="btn primary" type="submit">Subscribe</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Unsubscribe</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="unsubscribe">
      <label>Email<input type="email" name="email" required maxlength="190"></label>
      <p><button class="btn" type="submit">Unsubscribe</button></p>
    </form>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
