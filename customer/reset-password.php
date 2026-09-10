<?php
/**
 * Oyejo Gas - password-reset consume (Phase 5). Single-use token links.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done = false;
$row = null;

if ($token === '') {
    $errors[] = 'Missing reset token. Use the link from your email.';
} else {
    list($row, $err) = auth_get_reset($token);
    if (!$row) {
        $errors[] = $err;
    }
}

if ($row && request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $pw = (string) post('password', '');
        $pw2 = (string) post('password_confirm', '');
        if (strlen($pw) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors[] = 'Password must include a letter and a number.';
        } elseif ($pw !== $pw2) {
            $errors[] = 'Passwords do not match.';
        } else {
            list($row2, $err2) = auth_get_reset($token);
            if (!$row2) {
                $errors[] = $err2;
                $row = null;
            } else {
                auth_complete_reset($row2['id'], $row2['user_id'], $pw);
                auth_session_clear();
                $done = true;
            }
        }
    }
}

$page_title = 'Reset password';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Reset password</h1>
  <?php if ($done) : ?>
    <div class="card">
      <p>Your password was changed. Log in with the new one.</p>
      <p><a class="btn primary" href="<?= e(url('customer/login.php')) ?>">Log in</a></p>
    </div>
  <?php elseif (!$row) : ?>
    <?php foreach ($errors as $e) : ?>
      <div class="alert alert-error"><?= e($e) ?></div>
    <?php endforeach; ?>
    <p><a href="<?= e(url('customer/forgot-password.php')) ?>">Request a new link</a></p>
  <?php else : ?>
    <?php foreach ($errors as $e) : ?>
      <div class="alert alert-error"><?= e($e) ?></div>
    <?php endforeach; ?>
    <p>Setting a new password for <strong><?= e($row['email']) ?></strong>.</p>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>New password (min 8, letter + number)<input type="password" name="password" autocomplete="new-password" required></label>
      <label>Confirm new password<input type="password" name="password_confirm" autocomplete="new-password" required></label>
      <p><button class="btn primary" type="submit">Change password</button></p>
    </form>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
