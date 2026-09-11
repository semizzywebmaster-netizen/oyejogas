<?php
/**
 * Oyejo Gas - customer registration (Phase 5).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/referrals.php';

if (!oyejo_feature('customer_registration')) {
    http_response_code(403);
    $page_title = 'Registration disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Registration is currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}
if (is_logged_in()) {
    redirect(url('customer/'));
}

$errors = [];
$done = false;
$name = '';
$email = '';
$phone = '';
$username = '';
$whatsapp = '';
$refcode = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$ref_notice = '';

if (request_method() === 'POST') {
    [$rl_ok, $rl_retry] = rate_limit('register', rate_ip(), 10, 3600);
    if (!$rl_ok) {
        err_429($rl_retry);
    }
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $name = trim((string) post('name', ''));
        $email = trim((string) post('email', ''));
        $phone = trim((string) post('phone', ''));
        $username = trim((string) post('username', ''));
        $whatsapp = function_exists('growth_norm_phone') ? growth_norm_phone(post('whatsapp', '')) : trim((string) post('whatsapp', ''));
        $pw = (string) post('password', '');
        $pw2 = (string) post('password_confirm', '');
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Name must be 2–100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
            $errors[] = 'Phone number looks invalid.';
        }
        if ($username !== '' && (!function_exists('growth_valid_username') || !growth_valid_username($username))) {
            $errors[] = 'Username must start with a letter and be 3–30 letters, numbers or underscores.';
        }
        if ($whatsapp === '' || !function_exists('growth_valid_phone') || !growth_valid_phone($whatsapp)) {
            $errors[] = 'Enter a valid WhatsApp number.';
        }
        if (strlen($pw) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors[] = 'Password must include a letter and a number.';
        } elseif ($pw !== $pw2) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1');
            $s->execute([$email]);
            if ($s->fetch()) {
                $errors[] = 'That email is already registered. Try logging in.';
            }
        }
        if (!$errors && $phone !== '') {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `phone` = ? LIMIT 1');
            $s->execute([$phone]);
            if ($s->fetch()) {
                $errors[] = 'That phone number is already registered.';
            }
        }
        if (!$errors && $username !== '' && function_exists('growth_has_column') && growth_has_column('users', 'username')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1');
            $s->execute([$username]);
            if ($s->fetch()) {
                $errors[] = 'That username is already taken.';
            }
        }
        if (!$errors && $whatsapp !== '' && function_exists('growth_has_column') && growth_has_column('users', 'whatsapp')) {
            $s = db()->prepare('SELECT `id` FROM `users` WHERE `whatsapp` = ? LIMIT 1');
            $s->execute([$whatsapp]);
            if ($s->fetch()) {
                $errors[] = 'That WhatsApp number is already registered.';
            }
        }
        if (!$errors) {
            list($uid, $err) = auth_register_customer($name, $email, $phone, $pw, $username, $whatsapp);
            if (!$uid) {
                $errors[] = $err;
            } else {
                auth_send_verification(auth_db_user($uid));
                $rc = strtoupper(trim((string) post('refcode', $refcode)));
                if ($rc !== '' && oyejo_feature('referrals')) {
                    $sc = db()->prepare('SELECT `id` FROM `customers` WHERE `user_id` = ?');
                    $sc->execute([$uid]);
                    [$ref_ok, $ref_msg] = ref_capture($rc, (int) $sc->fetchColumn(), $uid);
                    if (!$ref_ok) {
                        $ref_notice = $ref_msg;
                    }
                }
                $done = true;
            }
        }
    }
}

$page_title = 'Create account';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Create account</h1>
  <?php if ($done) : ?>
    <div class="card">
      <h2 class="ok" style="color:#0b6b3a">Check your email</h2>
      <p>Your account was created. We sent a verification link to
        <strong><?= e($email) ?></strong> — open it within 24 hours to activate
        your account, then log in and verify your WhatsApp number.</p>
      <?php if ($ref_notice !== '') : ?><p><?= e($ref_notice) ?></p><?php endif; ?>
      <p><a class="btn primary" href="<?= e(url('customer/login.php')) ?>">Go to login</a></p>
    </div>
  <?php else : ?>
    <?php foreach ($errors as $e) : ?>
      <div class="alert alert-error"><?= e($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <label>Full name<input name="name" value="<?= e($name) ?>" required maxlength="100"></label>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" required maxlength="190"></label>
      <label>Username (optional, 3–30 letters)<input name="username" value="<?= e($username) ?>" maxlength="30" autocomplete="username"></label>
      <label>WhatsApp number<input name="whatsapp" value="<?= e($whatsapp) ?>" required maxlength="30" placeholder="e.g. +2348012345678"></label>
      <label>Phone (optional)<input name="phone" value="<?= e($phone) ?>" maxlength="30"></label>
      <label>Password (min 8, letter + number)<input type="password" name="password" autocomplete="new-password" required></label>
      <label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" required></label>
      <label>Referral code (optional)<input name="refcode" value="<?= e($refcode) ?>" maxlength="20"></label>
      <p><button class="btn primary" type="submit">Create account</button></p>
    </form>
    <p>Already registered? <a href="<?= e(url('customer/login.php')) ?>">Log in</a></p>
  <?php endif; ?>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
