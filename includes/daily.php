<?php
/**
 * Oyejo Gas - daily check-in, streaks and missions.
 *
 * Rewards are computed and paid server-side into the wallet (promo credits).
 * The browser never chooses the amount. One check-in per Lagos calendar day
 * is enforced by UNIQUE(customer_id, checkin_date).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function daily_enabled() {
    return oyejo_feature('daily_rewards') && oyejo_feature('customer_wallet');
}

function daily_today() {
    return date('Y-m-d');
}

function daily_defaults() {
    return [
        'daily_base_reward_minor' => 5000,       // ₦50
        'daily_streak_step_minor' => 1500,       // +₦15 per extra streak day
        'daily_streak_step_cap' => 6,            // extra days counted
        'daily_week_bonus_minor' => 25000,       // ₦250 every 7th day
        'daily_meter_target_minor' => 650000,    // ₦6,500 ≈ 6kg refill
        'daily_mission_shop_minor' => 2000,      // ₦20
        'daily_mission_refer_minor' => 2000,     // ₦20
        'daily_mission_profile_minor' => 10000,  // ₦100 once
        'daily_mission_order_minor' => 20000,    // ₦200
        'daily_mystery_chance' => 10,            // 1 in 10
        'daily_mystery_min_minor' => 5000,       // ₦50
        'daily_mystery_max_minor' => 20000,      // ₦200
    ];
}

function daily_settings() {
    $out = daily_defaults();
    try {
        $keys = array_keys($out);
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $s = db()->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($ph)");
        $s->execute($keys);
        foreach ($s->fetchAll() as $r) {
            if (is_numeric($r['value'])) {
                $out[$r['key']] = (int) $r['value'];
            }
        }
    } catch (Throwable $t) {
        // Defaults stand.
    }
    $out['daily_base_reward_minor'] = max(0, min(10000000, $out['daily_base_reward_minor']));
    $out['daily_streak_step_minor'] = max(0, min(10000000, $out['daily_streak_step_minor']));
    $out['daily_streak_step_cap'] = max(0, min(30, $out['daily_streak_step_cap']));
    $out['daily_week_bonus_minor'] = max(0, min(10000000, $out['daily_week_bonus_minor']));
    $out['daily_meter_target_minor'] = max(10000, min(100000000, $out['daily_meter_target_minor']));
    $out['daily_mystery_chance'] = max(0, min(100, $out['daily_mystery_chance']));
    $out['daily_mystery_min_minor'] = max(0, min(10000000, $out['daily_mystery_min_minor']));
    $out['daily_mystery_max_minor'] = max($out['daily_mystery_min_minor'], min(10000000, $out['daily_mystery_max_minor']));
    foreach (['daily_mission_shop_minor', 'daily_mission_refer_minor', 'daily_mission_profile_minor', 'daily_mission_order_minor'] as $k) {
        $out[$k] = max(0, min(10000000, $out[$k]));
    }
    return $out;
}

/**
 * Pure reward math. $mystery_roll is 1..100 or null (drawn with random_int).
 * Returns [base, streak_bonus, week_bonus, mystery, total].
 */
function daily_compute_reward($streak, array $cfg, $mystery_roll = null) {
    $streak = max(1, (int) $streak);
    $base = (int) $cfg['daily_base_reward_minor'];
    $extra_days = min($streak - 1, (int) $cfg['daily_streak_step_cap']);
    $streak_bonus = $extra_days * (int) $cfg['daily_streak_step_minor'];
    $week_bonus = ($streak % 7 === 0) ? (int) $cfg['daily_week_bonus_minor'] : 0;
    $mystery = 0;
    $chance = (int) $cfg['daily_mystery_chance'];
    if ($chance > 0) {
        if ($mystery_roll === null) {
            $mystery_roll = random_int(1, 100);
        }
        if ((int) $mystery_roll <= $chance) {
            $lo = (int) $cfg['daily_mystery_min_minor'];
            $hi = (int) $cfg['daily_mystery_max_minor'];
            $mystery = ($lo === $hi) ? $lo : random_int($lo, $hi);
        }
    }
    $total = $base + $streak_bonus + $week_bonus + $mystery;
    return [$base, $streak_bonus, $week_bonus, $mystery, $total];
}

function daily_customer_id($user_id) {
    $s = db()->prepare('SELECT `id` FROM `customers` WHERE `user_id` = ? LIMIT 1');
    $s->execute([(int) $user_id]);
    return (int) $s->fetchColumn();
}

function daily_last($customer_id) {
    $s = db()->prepare('SELECT * FROM `daily_checkins` WHERE `customer_id` = ? ORDER BY `checkin_date` DESC LIMIT 1');
    $s->execute([(int) $customer_id]);
    return $s->fetch() ?: null;
}

function daily_today_row($customer_id) {
    $s = db()->prepare('SELECT * FROM `daily_checkins` WHERE `customer_id` = ? AND `checkin_date` = ? LIMIT 1');
    $s->execute([(int) $customer_id, daily_today()]);
    return $s->fetch() ?: null;
}

/** Next streak if they check in today. */
function daily_next_streak($customer_id) {
    $last = daily_last($customer_id);
    if (!$last) {
        return 1;
    }
    $today = daily_today();
    if ($last['checkin_date'] === $today) {
        return (int) $last['streak'];
    }
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    if ($last['checkin_date'] === $yesterday) {
        return (int) $last['streak'] + 1;
    }
    return 1;
}

function daily_claimed_today($customer_id) {
    return daily_today_row($customer_id) !== null;
}

function daily_should_nudge($user_id = null) {
    if (!daily_enabled() || !is_logged_in()) {
        return false;
    }
    try {
        $uid = $user_id !== null ? (int) $user_id : (int) (current_user()['id'] ?? 0);
        $cid = daily_customer_id($uid);
        if ($cid < 1) {
            return false;
        }
        return !daily_claimed_today($cid);
    } catch (Throwable $t) {
        return false;
    }
}

function daily_audit($action, $user_id, $entity_id, $old, $new) {
    try {
        db()->prepare(
            'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $user_id ?: null, substr($action, 0, 100), 'daily', $entity_id ?: null,
            $old === null ? null : json_encode($old),
            $new === null ? null : json_encode($new),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $t) {
        error_log('[oyejo] daily_audit failed');
    }
}

/**
 * Claim today's check-in. Returns [ok, message, payload|null].
 */
function daily_checkin($customer_id, $user_id) {
    if (!daily_enabled()) {
        return [false, 'Daily rewards are currently disabled.', null];
    }
    $customer_id = (int) $customer_id;
    if ($customer_id < 1) {
        return [false, 'Customer account missing.', null];
    }
    require_once BASE_PATH . '/includes/wallet.php';
    $today = daily_today();
    if (daily_claimed_today($customer_id)) {
        return [false, 'You already claimed today’s credit. Come back tomorrow.', null];
    }
    [$ok, $retry] = rate_limit('daily_checkin', 'c:' . $customer_id, 8, 86400);
    if (!$ok) {
        return [false, 'Too many attempts. Try again in ' . $retry . ' seconds.', null];
    }
    $streak = daily_next_streak($customer_id);
    $cfg = daily_settings();
    [$base, $step, $week, $mystery, $total] = daily_compute_reward($streak, $cfg);
    if ($total <= 0) {
        return [false, 'Daily rewards are not configured.', null];
    }
    $ref = 'DCHK-' . $customer_id . '-' . str_replace('-', '', $today);
    [$txn_id, $errs, $dup] = wallet_credit($customer_id, 'promo', $total, [
        'reference' => $ref,
        'narration' => 'Daily check-in day ' . $streak . ($mystery > 0 ? ' + mystery bonus' : ''),
        'related_type' => 'daily_checkin',
        'related_id' => $customer_id,
        'created_by' => $user_id ?: null,
        'meta' => json_encode(['streak' => $streak, 'base' => $base, 'step' => $step, 'week' => $week, 'mystery' => $mystery]),
    ]);
    if (!$txn_id) {
        return [false, $errs[0] ?? 'Could not credit your wallet.', null];
    }
    try {
        db()->prepare(
            'INSERT INTO `daily_checkins` (`customer_id`, `checkin_date`, `streak`, `reward_minor`, `bonus_minor`, `wallet_txn_ref`, `ip_address`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $customer_id, $today, $streak, $total, $mystery, $ref,
            isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
        ]);
        $id = (int) db()->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, 'You already claimed today’s credit. Come back tomorrow.', null];
        }
        error_log('[oyejo] daily_checkin insert: ' . $e->getMessage());
        return [false, 'Could not record your check-in. Please try again.', null];
    }
    daily_audit('daily.checkin', $user_id, $id, null, ['streak' => $streak, 'amount' => $total, 'mystery' => $mystery, 'dup' => $dup]);
    try {
        $bal = (int) db()->query('SELECT `balance_minor` FROM `wallets` WHERE `customer_id` = ' . $customer_id)->fetchColumn();
        notify_send('wallet_transaction', $customer_id, [
            'direction' => 'credited', 'amount' => format_money($total),
            'balance' => format_money($bal), 'note' => 'Daily check-in streak ' . $streak . '.',
        ], 'Daily gas credit: ' . format_money($total),
            'You claimed ' . format_money($total) . ' for checking in today. Streak: ' . $streak . ' day(s).');
    } catch (Throwable $t) {
        // Credit already posted.
    }
    $msg = 'Credited ' . format_money($total) . ' · streak ' . $streak;
    if ($week > 0) {
        $msg .= ' · 7-day bonus!';
    }
    if ($mystery > 0) {
        $msg .= ' · mystery bonus ' . format_money($mystery) . '!';
    }
    return [true, $msg, [
        'streak' => $streak, 'total' => $total, 'base' => $base,
        'step' => $step, 'week' => $week, 'mystery' => $mystery,
    ]];
}

function daily_mark_seen($key) {
    $allowed = ['shop', 'refer'];
    if (!in_array($key, $allowed, true)) {
        return;
    }
    $_SESSION['daily_seen_' . $key] = daily_today();
}

function daily_seen($key) {
    return (($_SESSION['daily_seen_' . $key] ?? '') === daily_today());
}

function daily_missions() {
    $cfg = daily_settings();
    return [
        'shop' => ['Visit the shop', 'Browse LPG products today.', (int) $cfg['daily_mission_shop_minor'], 'daily'],
        'refer' => ['Open your referral link', 'Share gas credits with a friend.', (int) $cfg['daily_mission_refer_minor'], 'daily'],
        'profile' => ['Finish your profile', 'Verified phone + a default address.', (int) $cfg['daily_mission_profile_minor'], 'once'],
        'order' => ['Place an order today', 'Checkout a refill, cylinder or accessory.', (int) $cfg['daily_mission_order_minor'], 'daily'],
    ];
}

function daily_mission_claimed($customer_id, $key, $once = false) {
    if ($once) {
        $s = db()->prepare('SELECT 1 FROM `daily_mission_claims` WHERE `customer_id` = ? AND `mission_key` = ? LIMIT 1');
        $s->execute([(int) $customer_id, $key]);
        return (bool) $s->fetchColumn();
    }
    $s = db()->prepare('SELECT 1 FROM `daily_mission_claims` WHERE `customer_id` = ? AND `mission_key` = ? AND `claim_date` = ? LIMIT 1');
    $s->execute([(int) $customer_id, $key, daily_today()]);
    return (bool) $s->fetchColumn();
}

function daily_profile_complete($customer_id) {
    $s = db()->prepare(
        'SELECT u.`phone_verified_at`,
                (SELECT COUNT(*) FROM `customer_addresses` a WHERE a.`customer_id` = c.`id` AND a.`is_default` = 1) AS def_addr
         FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?'
    );
    $s->execute([(int) $customer_id]);
    $r = $s->fetch();
    return $r && $r['phone_verified_at'] && (int) $r['def_addr'] > 0;
}

function daily_ordered_today($customer_id) {
    $s = db()->prepare(
        "SELECT COUNT(*) FROM `orders` WHERE `customer_id` = ? AND DATE(`created_at`) = ? AND `status` NOT IN ('cancelled','failed')"
    );
    $s->execute([(int) $customer_id, daily_today()]);
    return (int) $s->fetchColumn() > 0;
}

function daily_mission_ready($customer_id, $key) {
    if ($key === 'shop') {
        return daily_seen('shop');
    }
    if ($key === 'refer') {
        return daily_seen('refer');
    }
    if ($key === 'profile') {
        return daily_profile_complete($customer_id);
    }
    if ($key === 'order') {
        return daily_ordered_today($customer_id);
    }
    return false;
}

/**
 * Claim a completed mission. Returns [ok, message].
 */
function daily_claim_mission($customer_id, $user_id, $key) {
    if (!daily_enabled()) {
        return [false, 'Daily rewards are currently disabled.'];
    }
    $catalog = daily_missions();
    if (!isset($catalog[$key])) {
        return [false, 'Unknown mission.'];
    }
    [$label, , $amount, $mode] = $catalog[$key];
    $once = $mode === 'once';
    if ($amount <= 0) {
        return [false, 'That mission has no reward configured.'];
    }
    if (daily_mission_claimed($customer_id, $key, $once)) {
        return [false, $once ? 'You already claimed this mission.' : 'You already claimed this mission today.'];
    }
    if (!daily_mission_ready($customer_id, $key)) {
        return [false, 'Finish the mission first, then claim.'];
    }
    require_once BASE_PATH . '/includes/wallet.php';
    $today = daily_today();
    $ref = 'DMSN-' . (int) $customer_id . '-' . $key . '-' . ($once ? 'ONCE' : str_replace('-', '', $today));
    [$txn_id, $errs] = wallet_credit((int) $customer_id, 'promo', $amount, [
        'reference' => $ref,
        'narration' => 'Daily mission: ' . $label,
        'related_type' => 'daily_mission',
        'related_id' => (int) $customer_id,
        'created_by' => $user_id ?: null,
        'meta' => json_encode(['mission' => $key]),
    ]);
    if (!$txn_id) {
        return [false, $errs[0] ?? 'Could not credit your wallet.'];
    }
    try {
        db()->prepare(
            'INSERT INTO `daily_mission_claims` (`customer_id`, `mission_key`, `claim_date`, `reward_minor`, `wallet_txn_ref`)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([(int) $customer_id, $key, $once ? '1970-01-01' : $today, $amount, $ref]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, 'You already claimed this mission.'];
        }
        error_log('[oyejo] daily_claim_mission: ' . $e->getMessage());
        return [false, 'Could not record the claim. Please try again.'];
    }
    daily_audit('daily.mission', $user_id, (int) $customer_id, null, ['mission' => $key, 'amount' => $amount]);
    return [true, 'Claimed ' . format_money($amount) . ' for “' . $label . '”.'];
}

function daily_week($customer_id) {
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime(daily_today() . ' -' . $i . ' day'));
        $out[$d] = false;
    }
    $from = array_key_first($out);
    $s = db()->prepare('SELECT `checkin_date` FROM `daily_checkins` WHERE `customer_id` = ? AND `checkin_date` >= ?');
    $s->execute([(int) $customer_id, $from]);
    foreach ($s->fetchAll() as $r) {
        if (isset($out[$r['checkin_date']])) {
            $out[$r['checkin_date']] = true;
        }
    }
    return $out;
}

function daily_history($customer_id, $limit = 30) {
    $s = db()->prepare(
        'SELECT * FROM `daily_checkins` WHERE `customer_id` = ? ORDER BY `checkin_date` DESC LIMIT ' . max(1, min(90, (int) $limit))
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}

function daily_meter($customer_id) {
    $s = db()->prepare(
        "SELECT COALESCE(SUM(`amount_minor`), 0) FROM `wallet_transactions` t
         JOIN `wallets` w ON w.`id` = t.`wallet_id`
         WHERE w.`customer_id` = ? AND t.`type` = 'promo' AND t.`status` = 'completed'
           AND t.`related_type` IN ('daily_checkin','daily_mission')"
    );
    $s->execute([(int) $customer_id]);
    $earned = (int) $s->fetchColumn();
    $target = (int) daily_settings()['daily_meter_target_minor'];
    $pct = $target > 0 ? min(100, (int) floor(($earned / $target) * 100)) : 0;
    return [$earned, $target, $pct];
}

function daily_checked_in_count() {
    return (int) db()->query(
        'SELECT COUNT(*) FROM `daily_checkins` WHERE `checkin_date` = ' . db()->quote(daily_today())
    )->fetchColumn();
}

function daily_admin_today($limit = 100) {
    $s = db()->prepare(
        'SELECT d.*, u.`name`, u.`email`, c.`customer_code`
         FROM `daily_checkins` d
         JOIN `customers` c ON c.`id` = d.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id`
         WHERE d.`checkin_date` = ? ORDER BY d.`id` DESC LIMIT ' . max(1, min(300, (int) $limit))
    );
    $s->execute([daily_today()]);
    return $s->fetchAll();
}

function daily_admin_stats() {
    $pdo = db();
    $today = daily_today();
    return [
        'today' => (int) $pdo->query('SELECT COUNT(*) FROM `daily_checkins` WHERE `checkin_date` = ' . $pdo->quote($today))->fetchColumn(),
        'week' => (int) $pdo->query('SELECT COUNT(*) FROM `daily_checkins` WHERE `checkin_date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)')->fetchColumn(),
        'paid_today' => (int) $pdo->query('SELECT COALESCE(SUM(`reward_minor`),0) FROM `daily_checkins` WHERE `checkin_date` = ' . $pdo->quote($today))->fetchColumn(),
        'longest' => (int) $pdo->query('SELECT COALESCE(MAX(`streak`),0) FROM `daily_checkins`')->fetchColumn(),
    ];
}

/**
 * Queue “don’t break your streak” reminders for customers who have a
 * live streak and have not checked in today. Idempotent per day.
 */
function daily_queue_reminders($cap = 200) {
    if (!daily_enabled()) {
        return 0;
    }
    $yesterday = date('Y-m-d', strtotime(daily_today() . ' -1 day'));
    $today = daily_today();
    $rows = db()->prepare(
        'SELECT d.`customer_id`, d.`streak` FROM `daily_checkins` d
         WHERE d.`checkin_date` = ?
           AND NOT EXISTS (SELECT 1 FROM `daily_checkins` x WHERE x.`customer_id` = d.`customer_id` AND x.`checkin_date` = ?)
           AND NOT EXISTS (SELECT 1 FROM `notifications` n WHERE n.`event` = \'promo\' AND n.`recipient` = CONCAT(\'daily:\', d.`customer_id`) AND n.`created_at` >= CURDATE())
         ORDER BY d.`id` DESC LIMIT ' . max(1, min(500, (int) $cap))
    );
    $rows->execute([$yesterday, $today]);
    $n = 0;
    $cfg = daily_settings();
    foreach ($rows->fetchAll() as $r) {
        $cid = (int) $r['customer_id'];
        $next = (int) $r['streak'] + 1;
        [$base, $step, $week, , $total] = daily_compute_reward($next, $cfg, 101); // no mystery in preview
        $queued = notify_send('promo', $cid, [
            'subject' => 'Your ' . (int) $r['streak'] . '-day streak is at risk',
            'message' => 'Claim today’s ' . format_money($total) . ' gas credit before midnight so your streak does not reset.',
        ], 'Your ' . (int) $r['streak'] . '-day streak is at risk',
            'Claim today’s ' . format_money($total) . ' gas credit before midnight so your streak does not reset.');
        if ($queued > 0) {
            db()->prepare(
                'INSERT INTO `notifications` (`customer_id`, `channel`, `event`, `recipient`, `subject`, `body`, `status`)
                 VALUES (?, \'push\', \'promo\', ?, \'sentinel\', \'sentinel\', \'delivered\')'
            )->execute([$cid, 'daily:' . $cid]);
            $n += $queued;
        }
    }
    return $n;
}

/**
 * Create tables + seed toggle/settings/permission on existing installs.
 * Fast no-op once the migration row exists.
 */
function daily_ensure_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $s = db()->prepare('SELECT 1 FROM `migrations` WHERE `migration` = ?');
        $s->execute(['20260911_daily_rewards']);
        if ($s->fetchColumn()) {
            return;
        }
    } catch (Throwable $t) {
        return; // installer / no DB
    }
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `daily_checkins` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INT UNSIGNED NOT NULL,
                `checkin_date` DATE NOT NULL,
                `streak` INT UNSIGNED NOT NULL DEFAULT 1,
                `reward_minor` INT NOT NULL DEFAULT 0,
                `bonus_minor` INT NOT NULL DEFAULT 0,
                `wallet_txn_ref` VARCHAR(40) NULL,
                `ip_address` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_checkin_day` (`customer_id`, `checkin_date`),
                KEY `idx_checkin_date` (`checkin_date`),
                CONSTRAINT `fk_checkin_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `daily_mission_claims` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INT UNSIGNED NOT NULL,
                `mission_key` VARCHAR(40) NOT NULL,
                `claim_date` DATE NOT NULL,
                `reward_minor` INT NOT NULL DEFAULT 0,
                `wallet_txn_ref` VARCHAR(40) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_mission_day` (`customer_id`, `mission_key`, `claim_date`),
                CONSTRAINT `fk_mission_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->prepare(
            'INSERT IGNORE INTO `feature_toggles` (`key`, `label`, `description`, `enabled`)
             VALUES (\'daily_rewards\', \'Daily rewards\', \'Daily check-in, streaks and missions that credit the wallet\', 1)'
        )->execute();
        $ins = db()->prepare(
            'INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, \'daily\')
             ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`)'
        );
        foreach (daily_defaults() as $k => $v) {
            $ins->execute([$k, (string) $v]);
        }
        db()->prepare(
            'INSERT IGNORE INTO `permissions` (`slug`, `name`, `group_name`, `description`)
             VALUES (\'daily.manage\', \'Manage daily rewards\', \'engagement\', \'View check-ins and configure daily earn\')'
        )->execute();
        db()->exec(
            "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
             SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
             WHERE p.`slug` = 'daily.manage' AND r.`slug` IN ('super_admin','admin','marketing_manager')"
        );
        db()->prepare('INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES (?, 2)')
            ->execute(['20260911_daily_rewards']);
        if (function_exists('rbac_refresh')) {
            rbac_refresh();
        }
    } catch (Throwable $t) {
        error_log('[oyejo] daily_ensure_schema: ' . $t->getMessage());
    }
}
