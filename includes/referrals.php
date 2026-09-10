<?php
/**
 * Oyejo Gas - referral codes, rewards and anti-fraud (Phase 21).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function ref_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'referral', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Referral settings with safe defaults. Amounts in minor units. */
function ref_settings() {
    $out = [
        'referral_reward_referrer_minor' => 50000,
        'referral_reward_referred_minor' => 25000,
        'referral_min_purchase_minor' => 200000,
        'referral_reward_expiry_days' => 30,
        'referral_velocity_24h' => 5,
    ];
    try {
        $stmt = db()->query('SELECT `key`, `value` FROM `settings` WHERE `key` LIKE \'referral\\_%\'');
        foreach ($stmt->fetchAll() as $r) {
            if (isset($out[$r['key']]) && is_numeric($r['value'])) {
                $out[$r['key']] = (int) $r['value'];
            }
        }
    } catch (Throwable $t) {
        // Defaults above.
    }
    return $out;
}

function ref_settings_save($data, $actor_id) {
    $fields = [
        'referral_reward_referrer_minor' => 'referrer reward (₦)',
        'referral_reward_referred_minor' => 'referred reward (₦)',
        'referral_min_purchase_minor' => 'minimum purchase (₦)',
    ];
    $updates = [];
    foreach ($fields as $k => $label) {
        if (!isset($data[$k])) {
            return [false, 'Missing ' . $label . '.'];
        }
        $v = (int) round(((float) $data[$k]) * 100);
        if ($v < 0 || $v > 100000000) {
            return [false, 'Invalid ' . $label . '.'];
        }
        $updates[$k] = $v;
    }
    $days = (int) ($data['referral_reward_expiry_days'] ?? 0);
    $vel = (int) ($data['referral_velocity_24h'] ?? 0);
    if ($days < 1 || $days > 365 || $vel < 1 || $vel > 1000) {
        return [false, 'Expiry must be 1–365 days and velocity 1–1000.'];
    }
    $updates['referral_reward_expiry_days'] = $days;
    $updates['referral_velocity_24h'] = $vel;
    foreach ($updates as $k => $v) {
        db()->prepare('INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, \'referrals\')
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$k, (string) $v]);
    }
    ref_audit('referral.settings', $actor_id, null, null, $updates);
    return [true, 'Referral settings saved.'];
}

function ref_notify($customer_id, $subject, $body) {
    try {
        $stmt = db()->prepare(
            'SELECT u.`email` FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?'
        );
        $stmt->execute([(int) $customer_id]);
        notify_emit((int) $customer_id, 'referral_reward', (string) $stmt->fetchColumn(), $subject, $body, 'email');
    } catch (Throwable $e) {
        error_log('ref_notify: ' . $e->getMessage());
    }
}

/**
 * Record a referral at registration (RR-02/RR-03/RR-10/RR-11/RR-12).
 * Always returns generic success to the new account; fraud only shows
 * on the staff desk.
 */
function ref_capture($code, $new_customer_id, $new_user_id) {
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return [true, ''];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT `id`, `user_id` FROM `customers` WHERE `referral_code` = ?');
    $stmt->execute([$code]);
    $referrer = $stmt->fetch();
    if (!$referrer) {
        return [true, '']; // unknown code: ignore silently
    }
    if ((int) $referrer['user_id'] === (int) $new_user_id || (int) $referrer['id'] === (int) $new_customer_id) {
        return [false, 'You cannot use your own referral code.'];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `referrals` WHERE `referred_customer_id` = ?');
    $stmt->execute([(int) $new_customer_id]);
    if ($stmt->fetchColumn()) {
        return [true, '']; // already attributed: keep the first referrer
    }
    $cfg = ref_settings();
    $flag = '';
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `referrals` WHERE `referrer_customer_id` = ? AND `created_at` >= NOW() - INTERVAL 1 DAY"
    );
    $stmt->execute([(int) $referrer['id']]);
    if ((int) $stmt->fetchColumn() >= $cfg['referral_velocity_24h']) {
        $flag = 'velocity: ' . $cfg['referral_velocity_24h'] . '+ referrals in 24h';
    }
    if ($flag === '') {
        $stmt = $pdo->prepare('SELECT `phone` FROM `users` WHERE `id` = ?');
        $stmt->execute([(int) $new_user_id]);
        $phone = (string) $stmt->fetchColumn();
        if ($phone !== '') {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM `customer_phones` WHERE `phone` = ? AND `customer_id` <> ? LIMIT 1'
            );
            $stmt->execute([$phone, (int) $new_customer_id]);
            if ($stmt->fetchColumn()) {
                $flag = 'duplicate_phone: number already belongs to another customer';
            }
        }
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO `referrals` (`referrer_customer_id`, `referred_customer_id`, `code_used`, `status`, `flag_note`)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $referrer['id'], (int) $new_customer_id, $code,
            $flag !== '' ? 'flagged' : 'pending', $flag !== '' ? $flag : null,
        ]);
        ref_audit('referral.capture', null, (int) $pdo->lastInsertId(), null,
            ['code' => $code, 'flagged' => $flag !== '']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [true, '']; // raced duplicate attribution: first wins
        }
        error_log('ref_capture: ' . $e->getMessage());
        return [true, ''];
    }
    return [true, ''];
}

/** Qualifying volume: delivered/completed order totals. */
function ref_order_volume($customer_id) {
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(`total_minor`), 0) FROM `orders`
         WHERE `customer_id` = ? AND `status` IN ('delivered','completed')"
    );
    $stmt->execute([(int) $customer_id]);
    return (int) $stmt->fetchColumn();
}

function ref_credit_reward($reward_id, $customer_id, $amount_minor, $campaign) {
    [$txn_id, $errs] = wallet_credit(
        (int) $customer_id, 'referral', (int) $amount_minor,
        [
            'reference' => 'REFCR-' . $reward_id . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'narration' => $campaign,
            'related_type' => 'referral_reward', 'related_id' => (int) $reward_id,
        ]
    );
    if (!$txn_id) {
        return [false, implode(' ', $errs)];
    }
    $ref = db()->query('SELECT `reference` FROM `wallet_transactions` WHERE `id` = ' . (int) $txn_id)->fetchColumn();
    db()->prepare("UPDATE `referral_rewards` SET `status` = 'credited', `wallet_txn_ref` = ? WHERE `id` = ?")
        ->execute([$ref, (int) $reward_id]);
    return [true, ''];
}

/**
 * Qualify due referrals and pay both sides (RR-04/RR-05/RR-06/RR-09).
 * Returns number of referrals qualified in this run.
 */
function ref_qualify_due() {
    $cfg = ref_settings();
    $pdo = db();
    $rows = $pdo->query("SELECT * FROM `referrals` WHERE `status` = 'pending' LIMIT 200")->fetchAll();
    $n = 0;
    foreach ($rows as $r) {
        if (ref_order_volume((int) $r['referred_customer_id']) < $cfg['referral_min_purchase_minor']) {
            continue;
        }
        $expires = date('Y-m-d H:i:s', time() + $cfg['referral_reward_expiry_days'] * 86400);
        $pdo->prepare("UPDATE `referrals` SET `status` = 'qualified', `qualified_at` = NOW() WHERE `id` = ? AND `status` = 'pending'")
            ->execute([(int) $r['id']]);
        $sides = [
            ['cid' => (int) $r['referrer_customer_id'], 'kind' => 'referrer', 'amt' => $cfg['referral_reward_referrer_minor']],
            ['cid' => (int) $r['referred_customer_id'], 'kind' => 'referred', 'amt' => $cfg['referral_reward_referred_minor']],
        ];
        $all_credited = true;
        foreach ($sides as $s) {
            $pdo->prepare(
                "INSERT INTO `referral_rewards` (`referral_id`, `customer_id`, `kind`, `amount_minor`, `status`, `expires_at`)
                 VALUES (?, ?, ?, ?, 'pending', ?)"
            )->execute([(int) $r['id'], $s['cid'], $s['kind'], $s['amt'], $expires]);
            $rid = (int) $pdo->lastInsertId();
            [$ok] = ref_credit_reward($rid, $s['cid'], $s['amt'], 'Referral reward (' . $s['kind'] . ')');
            if ($ok) {
                ref_notify($s['cid'], 'Referral reward received',
                    'You earned ' . format_money($s['amt']) . ' in referral rewards.');
            } else {
                $all_credited = false;
            }
        }
        if ($all_credited) {
            $pdo->prepare("UPDATE `referrals` SET `status` = 'rewarded' WHERE `id` = ?")->execute([(int) $r['id']]);
        }
        ref_audit('referral.qualify', null, (int) $r['id'], ['status' => 'pending'],
            ['status' => $all_credited ? 'rewarded' : 'qualified']);
        $n++;
    }
    return $n;
}

/** Claim a deferred (pending) reward. */
function ref_claim($reward_id, $customer_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `referral_rewards` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $reward_id, (int) $customer_id]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'Reward not found.'];
    }
    if ($r['status'] !== 'pending') {
        return [false, 'Only pending rewards can be claimed.'];
    }
    if ($r['expires_at'] !== null && $r['expires_at'] < date('Y-m-d H:i:s')) {
        $pdo->prepare("UPDATE `referral_rewards` SET `status` = 'expired' WHERE `id` = ?")->execute([(int) $reward_id]);
        return [false, 'This reward has expired.'];
    }
    [$ok, $err] = ref_credit_reward((int) $reward_id, (int) $customer_id, (int) $r['amount_minor'], 'Referral reward');
    if (!$ok) {
        return [false, $err];
    }
    ref_notify((int) $customer_id, 'Referral reward received',
        'You earned ' . format_money((int) $r['amount_minor']) . ' in referral rewards.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `referral_rewards` WHERE `referral_id` = ? AND `status` <> 'credited'");
    $stmt->execute([(int) $r['referral_id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare("UPDATE `referrals` SET `status` = 'rewarded' WHERE `id` = ?")->execute([(int) $r['referral_id']]);
    }
    ref_audit('referral.claim', null, (int) $reward_id, ['status' => 'pending'], ['status' => 'credited']);
    return [true, 'Reward credited to your wallet.'];
}

/** Expire due pending rewards (RR-08). Returns count. */
function ref_expire_due() {
    $stmt = db()->prepare(
        "UPDATE `referral_rewards` SET `status` = 'expired'
         WHERE `status` = 'pending' AND `expires_at` IS NOT NULL AND `expires_at` < NOW()"
    );
    $stmt->execute();
    return $stmt->rowCount();
}

/** Reverse a credited reward (staff). */
function ref_reverse($reward_id, $staff_id, $reason) {
    $reason = trim((string) $reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
        return [false, 'A reason of 5–255 characters is required.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `referral_rewards` WHERE `id` = ?');
    $stmt->execute([(int) $reward_id]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'Reward not found.'];
    }
    if ($r['status'] !== 'credited') {
        return [false, 'Only credited rewards can be reversed.'];
    }
    if (!empty($r['wallet_txn_ref'])) {
        $stmt = $pdo->prepare('SELECT `id` FROM `wallet_transactions` WHERE `reference` = ?');
        $stmt->execute([$r['wallet_txn_ref']]);
        $txn_id = $stmt->fetchColumn();
        if ($txn_id) {
            [$ok, $msg] = wallet_reverse((int) $txn_id, (int) $staff_id, $reason);
            if (!$ok) {
                return [false, $msg];
            }
        }
    }
    $pdo->prepare("UPDATE `referral_rewards` SET `status` = 'reversed' WHERE `id` = ?")->execute([(int) $reward_id]);
    ref_audit('referral.reverse', $staff_id, (int) $reward_id, ['status' => 'credited'], ['status' => 'reversed']);
    return [true, 'Reward reversed.'];
}

/** Flag / unflag a referral (staff, RR-12/RR-13). */
function ref_flag($referral_id, $staff_id, $note) {
    $note = mb_substr(trim((string) $note), 0, 255);
    if (mb_strlen($note) < 5) {
        return [false, 'A note of 5–255 characters is required.'];
    }
    $stmt = db()->prepare('SELECT `status` FROM `referrals` WHERE `id` = ?');
    $stmt->execute([(int) $referral_id]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        return [false, 'Referral not found.'];
    }
    if (!in_array($st, ['pending', 'qualified'], true)) {
        return [false, 'Only pending or qualified referrals can be flagged.'];
    }
    db()->prepare("UPDATE `referrals` SET `status` = 'flagged', `flag_note` = ? WHERE `id` = ?")
        ->execute([$note, (int) $referral_id]);
    ref_audit('referral.flag', $staff_id, (int) $referral_id, ['status' => $st], ['status' => 'flagged']);
    return [true, 'Referral flagged.'];
}

function ref_unflag($referral_id, $staff_id) {
    $stmt = db()->prepare('SELECT `status` FROM `referrals` WHERE `id` = ?');
    $stmt->execute([(int) $referral_id]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        return [false, 'Referral not found.'];
    }
    if ($st !== 'flagged') {
        return [false, 'Only flagged referrals can be cleared.'];
    }
    db()->prepare("UPDATE `referrals` SET `status` = 'pending', `flag_note` = NULL WHERE `id` = ?")
        ->execute([(int) $referral_id]);
    ref_audit('referral.unflag', $staff_id, (int) $referral_id, ['status' => 'flagged'], ['status' => 'pending']);
    return [true, 'Referral cleared back to pending.'];
}

/* ---------------- history & reports (RR-07) ---------------- */

function ref_for_referrer($customer_id, $limit = 50) {
    $stmt = db()->prepare(
        'SELECT r.*, cu.`customer_code`, u.`name` AS referred_name
         FROM `referrals` r JOIN `customers` cu ON cu.`id` = r.`referred_customer_id`
         JOIN `users` u ON u.`id` = cu.`user_id`
         WHERE r.`referrer_customer_id` = ? ORDER BY r.`id` DESC LIMIT ' . max(1, min(200, (int) $limit))
    );
    $stmt->execute([(int) $customer_id]);
    return $stmt->fetchAll();
}

function ref_rewards_for($customer_id, $limit = 50) {
    $stmt = db()->prepare(
        'SELECT * FROM `referral_rewards` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT ' . max(1, min(200, (int) $limit))
    );
    $stmt->execute([(int) $customer_id]);
    return $stmt->fetchAll();
}

function ref_all($status = '', $limit = 100) {
    $sql = 'SELECT r.*, rf.`customer_code` AS referrer_code, ru.`name` AS referrer_name,
            rd.`customer_code` AS referred_code, du.`name` AS referred_name
            FROM `referrals` r
            JOIN `customers` rf ON rf.`id` = r.`referrer_customer_id`
            JOIN `users` ru ON ru.`id` = rf.`user_id`
            JOIN `customers` rd ON rd.`id` = r.`referred_customer_id`
            JOIN `users` du ON du.`id` = rd.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'qualified', 'rewarded', 'expired', 'flagged'], true)) {
        $sql .= ' AND r.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY r.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function ref_rewards_all($status = '', $limit = 100) {
    $sql = 'SELECT rr.*, u.`name` AS customer_name
            FROM `referral_rewards` rr JOIN `customers` c ON c.`id` = rr.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'credited', 'expired', 'reversed'], true)) {
        $sql .= ' AND rr.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY rr.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function ref_reports() {
    $pdo = db();
    return [
        'by_status' => $pdo->query('SELECT `status`, COUNT(*) AS n FROM `referrals` GROUP BY `status`')->fetchAll(),
        'rewards' => $pdo->query('SELECT `status`, COUNT(*) AS n, COALESCE(SUM(`amount_minor`), 0) AS total FROM `referral_rewards` GROUP BY `status`')->fetchAll(),
    ];
}
