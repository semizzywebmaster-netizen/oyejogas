<?php
/**
 * Oyejo Gas - email verification + activation (Phase 5).
 * GET with token consumes it; otherwise shows the resend form.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();

$state = 'form'; // form | activated | already | error | resent
$message = '';
$token = (string) ($_GET['token'] ?? '');

if ($token !== '') {
    list($uid, $err) = auth_parse_email_token($token);
    if (!$uid) {
        $state = 'error';
        $message = $err;
    } else {
        $u = auth_db_user($uid);
        if (!$u) {
            $state = 'error';
            $message = 'Account not found.';
        } elseif (!empty($u['email_verified_at'])) {
            $state = 'already';
        } elseif ($u['status'] !== 'pending') {
            $state = 'error';
            $message = 'This account cannot be activated. Contact support.';
        } else {
            db()->prepare('UPDATE `users` SET `email_verified_at` = NOW(), `status` = \'active\' WHERE `id` = ?')
                ->execute([$uid]);
            $state = 'activated';
        }
    }
} elseif (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $state = 'error';
        $message = 'Security token mismatch. Reload and try again.';
    } else {
        $email = trim((string) post('email', ''));
        $s = db()->prepare("SELECT * FROM `users` WHERE `email` = ? AND `status` = 'pending' LIMIT 1");
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u && empty($u['email_verified_at'])) {
            auth_send_verification($u);
        }
        $state = 'resent';
    }
}

$page_title = 'Verify email';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Verify email</h1>
  <?php if ($state === 'activated') : ?>
    <div class="card">
      <p>Your email is verified and your account is active. Welcome!</p>
      <p><a class="btn primary" href="<?= e(url('customer/login.php')) ?>">Log in</a></p>
    </div>
  <?php elseif ($state === 'already') : ?>
    <div class="card">
      <p>This email is already verified. You can log in.</p>
      <p><a class="btn primary" href="<?= e(url('customer/login.php')) ?>">Log in</a></p>
    </div>
  <?php elseif ($state === 'resent') : ?>
    <div class="card">
      <p>If an unverified account uses that email, a new link is on its way.</p>
      <p><a href="<?= e(url('customer/login.php')) ?>">Back to login</a></p>
    </div>
  <?php else : ?>
    <?php if ($state === 'error') : ?>
      <div class="alert alert-error"><?= e($message) ?></div>
    <?php endif; ?>
    <p>Did not get the link? Enter your email to receive a new one.</p>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" required maxlength="190"></label>
      <p><button class="btn primary" type="submit">Resend link</button></p>
    </form>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
