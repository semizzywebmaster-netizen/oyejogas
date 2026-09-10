<?php
/**
 * Oyejo Gas - account security: password change + login history (Phase 10).
 * Sessions are PHP-native (single active session); multi-device revocation
 * would need DB-backed sessions and is noted, not silently claimed.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');

$me = current_user();
$uid = (int) $me['id'];
$u = auth_db_user($uid);
$message = '';
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $current = (string) post('current_password', '');
        $pw = (string) post('password', '');
        $pw2 = (string) post('password_confirm', '');
        if (!password_verify($current, (string) ($u['password_hash'] ?? ''))) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($pw) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors[] = 'New password must include a letter and a number.';
        } elseif ($pw !== $pw2) {
            $errors[] = 'New passwords do not match.';
        } elseif (password_verify($pw, (string) ($u['password_hash'] ?? ''))) {
            $errors[] = 'New password must differ from the current one.';
        } else {
            db()->prepare('UPDATE `users` SET `password_hash` = ? WHERE `id` = ?')
                ->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
            $message = 'Password changed. You stay logged in on this device.';
        }
    }
}

$s = db()->prepare('SELECT `ip_address`, `success`, `created_at` FROM `login_attempts` WHERE `email` = ? ORDER BY `id` DESC LIMIT 10');
$s->execute([$u['email']]);
$attempts = $s->fetchAll();

$page_title = 'Security';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Security</p>
<h1>Security</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<div class="card">
  <h2>Change password</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
    <label>New password (min 8, letter + number)<input type="password" name="password" autocomplete="new-password" required></label>
    <label>Confirm new password<input type="password" name="password_confirm" autocomplete="new-password" required></label>
    <p><button class="btn primary" type="submit">Change password</button></p>
  </form>
</div>
<div class="card">
  <h2>Recent login activity</h2>
  <?php if (!$attempts) : ?><p class="result-meta">No recorded attempts.</p>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>When</th><th>IP</th><th>Result</th></tr></thead>
        <tbody>
          <?php foreach ($attempts as $a) : ?>
            <tr><td><?= e($a['created_at']) ?></td><td><?= e($a['ip_address']) ?></td><td><?= $a['success'] ? 'Success' : 'Failed' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p class="result-meta">One active session per login. If you suspect misuse, change your password and log out.</p>
  <p><a class="btn ghost" href="<?= e(url('customer/logout.php')) ?>">Log out</a></p>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
