<?php
/**
 * Oyejo Gas - login (Phase 5). Email-or-phone + password, throttled.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if (is_logged_in()) {
    redirect(safe_next($next, landing_for_role(current_user()['role'])));
}

$errors = [];
$identifier = '';

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $next = (string) post('next', '');
        $identifier = trim((string) post('identifier', ''));
        list($ok, $msg, $u) = auth_attempt_login($identifier, (string) post('password', ''));
        if ($ok) {
            redirect(safe_next($next, landing_for_role($u['role'])));
        }
        $errors[] = $msg;
    }
}

$page_title = 'Log in';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Log in</h1>
  <?php foreach ($errors as $e) : ?>
    <div class="alert alert-error"><?= e($e) ?></div>
  <?php endforeach; ?>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label>Email or phone<input name="identifier" value="<?= e($identifier) ?>" required maxlength="190" autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <p><button class="btn primary" type="submit">Log in</button></p>
  </form>
  <p><a href="<?= e(url('customer/forgot-password.php')) ?>">Forgot password?</a>
    &middot; <a href="<?= e(url('customer/verify-email.php')) ?>">Resend verification link</a></p>
  <p>New here? <a href="<?= e(url('customer/register.php')) ?>">Create an account</a></p>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
