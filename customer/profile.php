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
        $username = trim((string) post('username', ''));
        $whatsapp = function_exists('growth_norm_phone') ? growth_norm_phone(post('whatsapp', '')) : trim((string) post('whatsapp', ''));
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Name must be 2–100 characters.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
            $errors[] = 'Phone number looks invalid.';
        }
        if ($username !== '' && (!function_exists('growth_valid_username') || !growth_valid_username($username))) {
            $errors[] = 'Username must start with a letter and be 3–30 letters, numbers or underscores.';
        }
        if ($whatsapp !== '' && (!function_exists('growth_valid_phone') || !growth_valid_phone($whatsapp))) {
            $errors[] = 'WhatsApp number looks invalid.';
        }
        if (!$errors && $phone !== '' && $phone !== (string) ($u['phone'] ?? '')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `phone` = ? AND `id` <> ? LIMIT 1');
            $s->execute([$phone, $uid]);
            if ($s->fetch()) {
                $errors[] = 'That phone number is already in use.';
            }
        }
        if (!$errors && $username !== '' && function_exists('growth_has_column') && growth_has_column('users', 'username')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `username` = ? AND `id` <> ? LIMIT 1');
            $s->execute([$username, $uid]);
            if ($s->fetch()) {
                $errors[] = 'That username is already taken.';
            }
        }
        if (!$errors && $whatsapp !== '' && function_exists('growth_has_column') && growth_has_column('users', 'whatsapp')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `whatsapp` = ? AND `id` <> ? LIMIT 1');
            $s->execute([$whatsapp, $uid]);
            if ($s->fetch()) {
                $errors[] = 'That WhatsApp number is already in use.';
            }
        }
        if (!$errors) {
            $phone_changed = $phone !== (string) ($u['phone'] ?? '');
            $wa_changed = $whatsapp !== (string) ($u['whatsapp'] ?? '');
            $sql = 'UPDATE `users` SET `name` = ?, `phone` = ?, `phone_verified_at` = ?';
            $args = [$name, $phone !== '' ? $phone : null, $phone_changed ? null : $u['phone_verified_at']];
            if (function_exists('growth_has_column') && growth_has_column('users', 'username')) {
                $sql .= ', `username` = ?';
                $args[] = $username !== '' ? $username : null;
            }
            if (function_exists('growth_has_column') && growth_has_column('users', 'whatsapp')) {
                $sql .= ', `whatsapp` = ?';
                $args[] = $whatsapp !== '' ? $whatsapp : null;
            }
            if (function_exists('growth_has_column') && growth_has_column('users', 'whatsapp_verified_at')) {
                $sql .= ', `whatsapp_verified_at` = ?';
                $args[] = $wa_changed ? null : ($u['whatsapp_verified_at'] ?? null);
            }
            $sql .= ' WHERE `id` = ?';
            $args[] = $uid;
            db()->prepare($sql)->execute($args);
            $_SESSION['user']['name'] = $name;
            $u = auth_db_user($uid);
            $need = ($phone_changed && $phone !== '') || ($wa_changed && $whatsapp !== '');
            $message = 'Profile updated.' . ($need ? ' Please verify your number.' : '');
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
  <?php if (!empty($u['username'])) : ?>
    <p><strong>Username:</strong> <?= e($u['username']) ?></p>
  <?php endif; ?>
  <?php if (!empty($u['whatsapp'])) : ?>
    <p><strong>WhatsApp:</strong> <?= e($u['whatsapp']) ?>
      <?= !empty($u['whatsapp_verified_at']) ? '<span class="stock ok">Verified</span>' : '<a href="' . e(url('customer/verify-phone.php')) . '">Verify now</a>' ?></p>
  <?php endif; ?>
</div>
<form method="post" action="" class="stack">
  <?= csrf_field() ?>
  <label>Full name<input name="name" value="<?= e($u['name']) ?>" required maxlength="100"></label>
  <label>Username (3–30 letters, optional)<input name="username" value="<?= e((string) ($u['username'] ?? '')) ?>" maxlength="30" placeholder="e.g. semizzy"></label>
  <label>WhatsApp number<input name="whatsapp" value="<?= e((string) ($u['whatsapp'] ?? '')) ?>" maxlength="30" placeholder="+2348012345678"></label>
  <label>Phone (optional)<input name="phone" value="<?= e((string) ($u['phone'] ?? '')) ?>" maxlength="30"></label>
  <p><button class="btn primary" type="submit">Save changes</button></p>
</form>
<p class="result-meta">Your WhatsApp number is used for OTP codes, order updates and abandoned-cart reminders. Verify it to log in with WhatsApp.</p>
<?php require BASE_PATH . '/includes/footer.php'; ?>
