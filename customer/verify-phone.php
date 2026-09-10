<?php
/**
 * Oyejo Gas - phone verification (Phase 5). Login required.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();

$me = current_user();
$u = auth_db_user($me['id']);
$errors = [];
$info = '';

if ($u && empty($u['phone'])) {
    $errors[] = 'No phone number on your account yet. Phone management arrives in Phase 10.';
} elseif ($u && empty($u['phone_verified_at']) && request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'send') {
            list($ok, $msg) = auth_send_phone_code($u);
            if ($ok) {
                $info = 'Code sent to ' . $u['phone'] . '. It expires in 10 minutes.';
            } else {
                $errors[] = $msg;
            }
        } elseif ($action === 'check') {
            list($ok, $msg) = auth_check_phone_code((string) post('code', ''));
            if ($ok) {
                db()->prepare('UPDATE `users` SET `phone_verified_at` = NOW() WHERE `id` = ?')->execute([$u['id']]);
                $u = auth_db_user($u['id']);
                $info = 'Phone number verified.';
            } else {
                $errors[] = $msg;
            }
        }
    }
}

$page_title = 'Verify phone';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Verify phone</h1>
  <?php foreach ($errors as $e) : ?>
    <div class="alert alert-error"><?= e($e) ?></div>
  <?php endforeach; ?>
  <?php if ($info !== '') : ?>
    <div class="alert alert-success"><?= e($info) ?></div>
  <?php endif; ?>
  <?php if ($u && !empty($u['phone']) && !empty($u['phone_verified_at'])) : ?>
    <div class="card">
      <p><strong><?= e($u['phone']) ?></strong> is verified.</p>
      <p><a href="<?= e(url('customer/')) ?>">Back to My account</a></p>
    </div>
  <?php elseif ($u && !empty($u['phone'])) : ?>
    <div class="card">
      <h2>1. Send a code</h2>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <p><button class="btn ghost" type="submit">Send code to <?= e($u['phone']) ?></button></p>
      </form>
    </div>
    <div class="card">
      <h2>2. Enter the code</h2>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="check">
        <label>6-digit code<input name="code" inputmode="numeric" maxlength="6" required></label>
        <p><button class="btn primary" type="submit">Verify</button></p>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
