<?php
/**
 * Oyejo Gas - customer profile view/edit (Phase 10).
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
        $name = trim((string) post('name', ''));
        $phone = trim((string) post('phone', ''));
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Name must be 2–100 characters.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
            $errors[] = 'Phone number looks invalid.';
        }
        if (!$errors && $phone !== '' && $phone !== (string) ($u['phone'] ?? '')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `phone` = ? AND `id` <> ? LIMIT 1');
            $s->execute([$phone, $uid]);
            if ($s->fetch()) {
                $errors[] = 'That phone number is already in use.';
            }
        }
        if (!$errors) {
            $phone_changed = $phone !== (string) ($u['phone'] ?? '');
            db()->prepare('UPDATE `users` SET `name` = ?, `phone` = ?, `phone_verified_at` = ? WHERE `id` = ?')
                ->execute([$name, $phone !== '' ? $phone : null, $phone_changed ? null : $u['phone_verified_at'], $uid]);
            $_SESSION['user']['name'] = $name;
            $u = auth_db_user($uid);
            $message = 'Profile updated.' . ($phone_changed && $phone !== '' ? ' Please verify your new phone number.' : '');
        }
    }
}

$page_title = 'Profile';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Profile</p>
<h1>Profile</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<div class="card">
  <p><strong>Email:</strong> <?= e($u['email']) ?>
    <?= !empty($u['email_verified_at']) ? '<span class="stock ok">Verified</span>' : '<span class="stock low">Unverified</span>' ?></p>
  <?php if (!empty($u['phone'])) : ?>
    <p><strong>Phone:</strong> <?= e($u['phone']) ?>
      <?= !empty($u['phone_verified_at']) ? '<span class="stock ok">Verified</span>' : '<a href="' . e(url('customer/verify-phone.php')) . '">Verify now</a>' ?></p>
  <?php endif; ?>
</div>
<form method="post" action="" class="stack">
  <?= csrf_field() ?>
  <label>Full name<input name="name" value="<?= e($u['name']) ?>" required maxlength="100"></label>
  <label>Phone (optional)<input name="phone" value="<?= e((string) ($u['phone'] ?? '')) ?>" maxlength="30"></label>
  <p><button class="btn primary" type="submit">Save changes</button></p>
</form>
<?php require BASE_PATH . '/includes/footer.php'; ?>
