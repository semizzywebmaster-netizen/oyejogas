<?php
/**
 * Oyejo Gas - authentication library (Phase 5).
 * Registration, login, logout, password reset, email/phone verification,
 * session expiry, throttling and safe redirects. Loaded via bootstrap.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

define('AUTH_IDLE_TIMEOUT', 1800);      // 30 minutes
define('AUTH_ABSOLUTE_TIMEOUT', 43200); // 12 hours
define('AUTH_REGEN_EVERY', 900);        // re-issue session id every 15 min
define('AUTH_MAX_FAILS', 5);            // failures before lockout
define('AUTH_LOCK_MINUTES', 15);
define('AUTH_IP_MAX_FAILS', 20);        // per-IP failures before block

function auth_ua_hash() {
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'));
}

function auth_ip() {
    // Deliberately REMOTE_ADDR only (proxy headers are spoofable; Phase 27
    // documents trusted-proxy handling if ever needed).
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/**
 * Per-request session guard: idle + absolute expiry, device binding,
 * activity stamp, periodic id rotation. Silent (no redirect) so public
 * pages simply see a logged-out visitor; require_login() bounces.
 */
function auth_tick() {
    if (empty($_SESSION['user'])) {
        return;
    }
    $now = time();
    $loginAt = (int) ($_SESSION['login_at'] ?? $now);
    $last = (int) ($_SESSION['last_activity'] ?? $now);
    if (($now - $last) > AUTH_IDLE_TIMEOUT || ($now - $loginAt) > AUTH_ABSOLUTE_TIMEOUT) {
        auth_session_clear();
        flash('error', 'Your session expired. Please log in again.');
        return;
    }
    if (($_SESSION['ua'] ?? '') !== auth_ua_hash()) {
        auth_session_clear();
        flash('error', 'Session device changed. Please log in again.');
        return;
    }
    // Revalidate against the database: suspension or role change takes
    // effect on the very next request (privilege is server-side, Phase 6).
    try {
        $fresh = db()->prepare(
            'SELECT u.role_id, u.status, r.slug AS role FROM `users` u
             JOIN `roles` r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
        );
        $fresh->execute([(int) $_SESSION['user']['id']]);
        $row = $fresh->fetch();
        if (!$row || $row['status'] === 'suspended') {
            auth_session_clear();
            flash('error', 'Your account is no longer available. Please log in again.');
            return;
        }
        if ((int) $row['role_id'] !== (int) $_SESSION['user']['role_id']) {
            $_SESSION['user']['role_id'] = (int) $row['role_id'];
            $_SESSION['user']['role'] = (string) $row['role'];
            unset($_SESSION['perms'], $_SESSION['perms_role']);
        }
    } catch (Throwable $t) {
        // DB unreachable: keep the session (permission checks fail closed).
    }
    $_SESSION['last_activity'] = $now;
    if (($now - (int) ($_SESSION['last_regen'] ?? 0)) > AUTH_REGEN_EVERY) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = $now;
    }
}

/** Empty the session and rotate the id (no redirect). */
function auth_session_clear() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Full logout: wipe session + cookie + server data, then redirect. */
function auth_logout($msg = 'You have been logged out.', $to = null) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    session_start();
    if ($msg !== null && $msg !== '') {
        flash('info', $msg);
    }
    redirect($to === null ? url('') : $to);
}

// ------------------------------------------------------------- throttling
function auth_fail_count($field, $value, $minutes = 15) {
    if ($field !== 'email' && $field !== 'ip_address') {
        return 0;
    }
    // Window computed in SQL (NOW()) so PHP/DB clock skew can never widen it.
    $minutes = max(1, (int) $minutes);
    $s = db()->prepare(
        "SELECT COUNT(*) AS c FROM `login_attempts` WHERE `$field` = ? AND `success` = 0 AND `created_at` >= NOW() - INTERVAL $minutes MINUTE"
    );
    $s->execute([$value]);
    return (int) $s->fetch()['c'];
}

function auth_log_attempt($email, $success) {
    $s = db()->prepare(
        'INSERT INTO `login_attempts` (`email`, `ip_address`, `success`) VALUES (?, ?, ?)'
    );
    $s->execute([mb_substr((string) $email, 0, 190), mb_substr(auth_ip(), 0, 45), $success ? 1 : 0]);
}

// ------------------------------------------------------------------ users
function auth_find_user($identifier) {
    $s = db()->prepare(
        'SELECT u.*, r.slug AS role FROM `users` u JOIN `roles` r ON r.id = u.role_id
         WHERE u.email = ? OR u.phone = ? LIMIT 1'
    );
    $s->execute([$identifier, $identifier]);
    $row = $s->fetch();
    return $row ? $row : null;
}

function auth_db_user($id) {
    $s = db()->prepare(
        'SELECT u.*, r.slug AS role FROM `users` u JOIN `roles` r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
    );
    $s->execute([(int) $id]);
    $row = $s->fetch();
    return $row ? $row : null;
}

function auth_rand_code($len) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/** Register a customer (input must be validated first). [userId|null, error] */
function auth_register_customer($name, $email, $phone, $password) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $role = $pdo->query("SELECT `id` FROM `roles` WHERE `slug` = 'customer'")->fetch();
        if (!$role) {
            $pdo->rollBack();
            return [null, 'Customer role is missing. Re-run the installer.'];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO `users` (`role_id`, `name`, `email`, `phone`, `password_hash`, `status`)
             VALUES (?, ?, ?, ?, ?, \'pending\')'
        );
        $stmt->execute([
            $role['id'], $name, $email,
            $phone !== '' ? $phone : null,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $uid = (int) $pdo->lastInsertId();
        $done = false;
        for ($i = 0; $i < 5 && !$done; $i++) {
            try {
                $pdo->prepare(
                    'INSERT INTO `customers` (`user_id`, `customer_code`, `referral_code`) VALUES (?, ?, ?)'
                )->execute([$uid, 'C-' . auth_rand_code(6), auth_rand_code(8)]);
                $done = true;
            } catch (PDOException $e) {
                if ($i === 4) {
                    throw $e;
                }
            }
        }
        // Phase 11: every customer gets a wallet at registration (WL-01).
        $customer_id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO `wallets` (`customer_id`, `balance_minor`, `status`) VALUES (?, 0, 'active')")
            ->execute([$customer_id]);
        $pdo->commit();
        return [$uid, ''];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return [null, 'That email or phone is already registered. Try logging in.'];
        }
        report_error('auth', 'error', $e);
        return [null, 'Registration failed. Please try again.'];
    }
}

// ------------------------------------------------------------------- login
function auth_register_fail($u) {
    $fails = (int) $u['failed_logins'] + 1;
    $lock = $fails >= AUTH_MAX_FAILS
        ? date('Y-m-d H:i:s', time() + AUTH_LOCK_MINUTES * 60)
        : $u['locked_until'];
    db()->prepare('UPDATE `users` SET `failed_logins` = ?, `locked_until` = ? WHERE `id` = ?')
        ->execute([$fails, $lock, $u['id']]);
}

/**
 * Attempt login with email-or-phone + password.
 * Returns [ok, message, sessionUser|null].
 */
function auth_attempt_login($identifier, $password) {
    $identifier = trim((string) $identifier);
    if ($identifier === '' || $password === '') {
        return [false, 'Enter your email/phone and password.', null];
    }
    if (auth_fail_count('ip_address', auth_ip()) >= AUTH_IP_MAX_FAILS) {
        return [false, 'Too many attempts from your network. Try again later.', null];
    }
    if (auth_fail_count('email', mb_strtolower($identifier)) >= AUTH_MAX_FAILS) {
        return [false, 'Too many attempts. Try again in 15 minutes.', null];
    }
    $u = auth_find_user($identifier);
    if (!$u || !password_verify((string) $password, $u['password_hash'])) {
        if ($u) {
            auth_register_fail($u);
        }
        auth_log_attempt(mb_strtolower($identifier), false);
        return [false, 'Incorrect email/phone or password.', null];
    }
    if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()) {
        auth_log_attempt($u['email'], false);
        return [false, 'Account temporarily locked after too many attempts. Try again later.', null];
    }
    if ($u['status'] === 'suspended') {
        return [false, 'Your account has been suspended. Contact support for help.', null];
    }
    if ($u['status'] === 'pending') {
        return [false, 'Please verify your email to activate your account, or resend the link below.', null];
    }
    $pdo = db();
    session_regenerate_id(true);
    $pdo->prepare(
        'UPDATE `users` SET `failed_logins` = 0, `locked_until` = NULL,
         `last_login_at` = NOW(), `last_login_ip` = ? WHERE `id` = ?'
    )->execute([mb_substr(auth_ip(), 0, 45), $u['id']]);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE `users` SET `password_hash` = ? WHERE `id` = ?')
            ->execute([password_hash((string) $password, PASSWORD_DEFAULT), $u['id']]);
    }
    $_SESSION['user'] = [
        'id' => (int) $u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'phone' => $u['phone'],
        'role' => $u['role'],
        'role_id' => (int) $u['role_id'],
    ];
    $_SESSION['login_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regen'] = time();
    $_SESSION['ua'] = auth_ua_hash();
    auth_log_attempt($u['email'], true);
    return [true, '', $_SESSION['user']];
}

// ------------------------------------------------------ email verification
/** Stateless HMAC-signed token: userId.expiry.signature. No DB row needed. */
function auth_email_token($userId, $ttl = 86400) {
    $exp = time() + $ttl;
    return $userId . '.' . $exp . '.' . hash_hmac('sha256', $userId . '.' . $exp, APP_KEY);
}

function auth_parse_email_token($token) {
    $p = explode('.', (string) $token);
    if (count($p) !== 3 || !ctype_digit($p[0]) || !ctype_digit($p[1])) {
        return [null, 'Invalid verification link.'];
    }
    if ((int) $p[1] < time()) {
        return [null, 'Verification link expired. Request a new one below.'];
    }
    if (!hash_equals(hash_hmac('sha256', $p[0] . '.' . $p[1], APP_KEY), $p[2])) {
        return [null, 'Invalid verification link.'];
    }
    return [(int) $p[0], ''];
}

function auth_send_verification($user) {
    $link = url('customer/verify-email.php') . '?token=' . auth_email_token($user['id']);
    send_mail(
        $user['email'],
        'Verify your email',
        "Hello {$user['name']},\n\nVerify your email here: $link\n\nThis link expires in 24 hours."
    );
}

// --------------------------------------------------------- password reset
/** Always silent (never reveals whether the email exists). */
function auth_request_reset($email) {
    $s = db()->prepare(
        "SELECT * FROM `users` WHERE `email` = ? AND `status` IN ('active', 'pending') LIMIT 1"
    );
    $s->execute([trim((string) $email)]);
    $u = $s->fetch();
    if ($u) {
        $token = bin2hex(random_bytes(32));
        $pdo = db();
        $pdo->prepare('DELETE FROM `password_resets` WHERE `user_id` = ? AND `used_at` IS NULL')
            ->execute([$u['id']]);
        $pdo->prepare(
            'INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`) VALUES (?, ?, ?)'
        )->execute([$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
        $link = url('customer/reset-password.php') . '?token=' . $token;
        send_mail(
            $u['email'],
            'Reset your password',
            "Hello {$u['name']},\n\nReset your password here: $link\n\n"
            . "This link expires in 1 hour. Ignore this message if it was not you."
        );
    }
}

function auth_get_reset($token) {
    $s = db()->prepare(
        'SELECT pr.*, u.email, u.name, u.status FROM `password_resets` pr
         JOIN `users` u ON u.id = pr.user_id WHERE pr.token_hash = ? LIMIT 1'
    );
    $s->execute([hash('sha256', (string) $token)]);
    $r = $s->fetch();
    if (!$r || $r['used_at'] !== null) {
        return [null, 'Invalid or already-used reset link.'];
    }
    if (strtotime($r['expires_at']) < time()) {
        return [null, 'Reset link expired. Request a new one.'];
    }
    if ($r['status'] !== 'active' && $r['status'] !== 'pending') {
        return [null, 'This account is unavailable.'];
    }
    return [$r, ''];
}

function auth_complete_reset($resetId, $userId, $password) {
    $pdo = db();
    $pdo->prepare('UPDATE `users` SET `password_hash` = ?, `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ?')
        ->execute([password_hash((string) $password, PASSWORD_DEFAULT), (int) $userId]);
    $pdo->prepare('UPDATE `password_resets` SET `used_at` = NOW() WHERE `id` = ?')->execute([(int) $resetId]);
    $pdo->prepare('DELETE FROM `password_resets` WHERE `user_id` = ? AND `used_at` IS NULL')->execute([(int) $userId]);
}

// ------------------------------------------------------- phone verification
/**
 * Session-backed 6-digit codes (single-server safe) with cooldown, expiry
 * and attempt caps. Delivery goes through sms_send() so an SMS provider can
 * plug in during Phase 22 without touching this flow. Upgrade path: move
 * pending codes to a DB table via migration if multi-server is ever needed.
 */
function auth_send_phone_code($user) {
    $now = time();
    if (!empty($_SESSION['phone_cooldown']) && (int) $_SESSION['phone_cooldown'] > $now) {
        return [false, 'Please wait a minute before requesting another code.'];
    }
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['phone_code'] = hash('sha256', $code);
    $_SESSION['phone_exp'] = $now + 600;
    $_SESSION['phone_tries'] = 0;
    $_SESSION['phone_cooldown'] = $now + 60;
    sms_send($user['phone'], 'Your verification code is ' . $code . '. It expires in 10 minutes.');
    return [true, ''];
}

function auth_check_phone_code($code) {
    if (empty($_SESSION['phone_code']) || time() > (int) ($_SESSION['phone_exp'] ?? 0)) {
        return [false, 'Code expired. Request a new one.'];
    }
    if ((int) ($_SESSION['phone_tries'] ?? 0) >= 5) {
        return [false, 'Too many attempts. Request a new code.'];
    }
    $_SESSION['phone_tries'] = (int) ($_SESSION['phone_tries'] ?? 0) + 1;
    if (!hash_equals((string) $_SESSION['phone_code'], hash('sha256', trim((string) $code)))) {
        return [false, 'Incorrect code.'];
    }
    unset($_SESSION['phone_code'], $_SESSION['phone_exp'], $_SESSION['phone_tries']);
    return [true, ''];
}

// ---------------------------------------------------------------- redirects
/** Allow app-relative paths only; everything else falls back. No open redirects. */
function safe_next($url, $default) {
    $url = trim((string) $url);
    $bad = $url === ''
        || $url[0] !== '/'
        || strpos($url, '\\') !== false
        || strpos($url, '://') !== false
        || preg_match('/[\s\x00-\x1F\x7F]/', $url)
        || (isset($url[1]) && $url[1] === '/');
    return $bad ? $default : $url;
}

/** Role-based landing page after login. */
function landing_for_role($role) {
    $staff = [
        'super_admin', 'admin', 'manager', 'logistics_manager', 'customer_support',
        'marketing_manager', 'inventory_manager', 'finance_manager',
    ];
    if (in_array($role, $staff, true)) {
        return url('admin/');
    }
    if ($role === 'driver') {
        return url('driver/');
    }
    return url('customer/');
}
