<?php
/**
 * Oyejo Gas - password-reset request (Phase 5). Always silent.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
if (is_logged_in()) {
    redirect(url('customer/'));
}

$sent = false;
if (request_method() === 'POST') {
    [$rl_ok, $rl_retry] = rate_limit('pwreset', rate_ip(), 5, 3600);
    if (!$rl_ok) {
        err_429($rl_retry);
    }
    if (!csrf_verify(post('csrf_token'))) {
        flash('error', 'Security token mismatch. Reload and try again.');
        redirect(url('customer/forgot-password.php'));
    }
    auth_request_reset((string) post('email', ''));
    $sent = true;
}

$page_title = 'Forgot password';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Forgot password</h1>
  <?php if ($sent) : ?>
    <div class="card">
      <p>If an account uses that email address, a reset link is on its way.
        It expires in 1 hour.</p>
      <p><a href="<?= e(url('customer/login.php')) ?>">Back to login</a></p>
    </div>
  <?php else : ?>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" required maxlength="190"></label>
      <p><button class="btn primary" type="submit">Send reset link</button></p>
    </form>
    <p><a href="<?= e(url('customer/login.php')) ?>">Back to login</a></p>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
