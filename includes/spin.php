<?php
/**
 * Oyejo Gas - spin-to-win engine (Phase 20).
 * The result is ALWAYS drawn server-side with random_int(); the browser
 * only triggers the spin and displays the stored outcome (SP-13).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function spin_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'spin', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function spin_period_key($rule, $ts = null) {
    $ts = $ts ?: time();
    if ($rule === 'weekly') {
        return date('o-\\WW', $ts);
    }
    if ($rule === 'campaign') {
        return 'campaign';
    }
    return date('Y-m-d', $ts);
}

function spin_rules() {
    return ['daily' => 'Daily', 'weekly' => 'Weekly', 'campaign' => 'Once per campaign'];
}

function spin_reward_types() {
    return [
        'wallet_credit' => 'Wallet credit', 'promo_code' => 'Promo code',
        'discount' => 'Discount coupon', 'free_delivery' => 'Free-delivery coupon',
        'none' => 'No prize (try again)',
    ];
}

/* ---------------- campaigns (SP-01, SP-05, SP-10) ---------------- */

function spin_campaigns_all() {
    return db()->query('SELECT c.*, (SELECT COUNT(*) FROM `spin_prizes` p WHERE p.`campaign_id` = c.`id`) AS prizes,
        (SELECT COUNT(*) FROM `spins` s WHERE s.`campaign_id` = c.`id`) AS spins
        FROM `spin_campaigns` c ORDER BY c.`id` DESC')->fetchAll();
}

function spin_campaign_get($id) {
    $stmt = db()->prepare('SELECT * FROM `spin_campaigns` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

function spin_campaigns_live() {
    return db()->query(
        "SELECT * FROM `spin_campaigns` WHERE `is_active` = 1
         AND `starts_at` <= NOW() AND `ends_at` >= NOW() ORDER BY `id` DESC"
    )->fetchAll();
}

function spin_campaign_save($id, $data, $actor_id) {
    $slug = strtolower(trim((string) ($data['slug'] ?? '')));
    $name = trim((string) ($data['name'] ?? ''));
    $desc = trim((string) ($data['description'] ?? ''));
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $slug)) {
        return [false, 'Slug must be 1–100 chars: letters, numbers, underscore.'];
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 190) {
        return [false, 'Name must be 3–190 characters.'];
    }
    if (mb_strlen($desc) > 5000) {
        return [false, 'Description is too long (max 5000).'];
    }
    $starts = mk_dt($data['starts_at'] ?? '');
    $ends = mk_dt($data['ends_at'] ?? '');
    if ($starts === null || $starts === false || $ends === null || $ends === false) {
        return [false, 'Start and end dates are required (YYYY-MM-DD HH:MM:SS).'];
    }
    if ($ends <= $starts) {
        return [false, 'End date must be after start date.'];
    }
    $rule = (string) ($data['period_rule'] ?? 'daily');
    if (!isset(spin_rules()[$rule])) {
        return [false, 'Invalid spin period.'];
    }
    $min_order = (int) round(((float) ($data['min_order'] ?? 0)) * 100);
    if ($min_order < 0) {
        return [false, 'Minimum order cannot be negative.'];
    }
    $max_spins = trim((string) ($data['max_spins_per_user'] ?? ''));
    $max_spins = $max_spins === '' ? null : (int) $max_spins;
    if ($max_spins !== null && $max_spins < 1) {
        return [false, 'Max spins per user must be at least 1.'];
    }
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    $stmt = db()->prepare('SELECT `id` FROM `spin_campaigns` WHERE `slug` = ? AND `id` <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That slug is already used.'];
    }
    if ($id > 0) {
        if (!spin_campaign_get($id)) {
            return [false, 'Campaign not found.'];
        }
        db()->prepare(
            'UPDATE `spin_campaigns` SET `slug` = ?, `name` = ?, `description` = ?, `starts_at` = ?, `ends_at` = ?,
             `min_order_minor` = ?, `period_rule` = ?, `max_spins_per_user` = ?, `is_active` = ? WHERE `id` = ?'
        )->execute([$slug, $name, $desc ?: null, $starts, $ends, $min_order, $rule, $max_spins, $active, $id]);
        spin_audit('spin.campaign_save', $actor_id, $id, null, ['slug' => $slug]);
        return [true, 'Campaign saved.'];
    }
    db()->prepare(
        'INSERT INTO `spin_campaigns` (`slug`, `name`, `description`, `starts_at`, `ends_at`,
         `min_order_minor`, `period_rule`, `max_spins_per_user`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$slug, $name, $desc ?: null, $starts, $ends, $min_order, $rule, $max_spins, $active]);
    spin_audit('spin.campaign_create', $actor_id, (int) db()->lastInsertId(), null, ['slug' => $slug]);
    return [true, 'Campaign created.'];
}

function spin_campaign_delete($id, $actor_id) {
    $c = spin_campaign_get((int) $id);
    if (!$c) {
        return [false, 'Campaign not found.'];
    }
    try {
        db()->prepare('DELETE FROM `spin_campaigns` WHERE `id` = ?')->execute([(int) $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, 'Cannot delete: this campaign already has spins. Deactivate it instead.'];
        }
        throw $e;
    }
    spin_audit('spin.campaign_delete', $actor_id, (int) $id, ['slug' => $c['slug']], null);
    return [true, 'Campaign deleted.'];
}

/* ---------------- prizes (SP-02, SP-03, SP-04) ---------------- */

function spin_prizes($campaign_id) {
    $stmt = db()->prepare('SELECT * FROM `spin_prizes` WHERE `campaign_id` = ? ORDER BY `id`');
    $stmt->execute([(int) $campaign_id]);
    return $stmt->fetchAll();
}

function spin_prize_save($id, $campaign_id, $data, $actor_id) {
    $campaign_id = (int) $campaign_id;
    if (!spin_campaign_get($campaign_id)) {
        return [false, 'Campaign not found.'];
    }
    $label = trim((string) ($data['label'] ?? ''));
    $type = (string) ($data['reward_type'] ?? 'none');
    if (mb_strlen($label) < 3 || mb_strlen($label) > 150) {
        return [false, 'Label must be 3–150 characters.'];
    }
    if (!isset(spin_reward_types()[$type])) {
        return [false, 'Invalid reward type.'];
    }
    $value_minor = (int) round(((float) ($data['reward_value'] ?? 0)) * 100);
    $promo = strtoupper(trim((string) ($data['promo_code'] ?? '')));
    if ($type === 'wallet_credit' && $value_minor <= 0) {
        return [false, 'Wallet credit needs a positive ₦ value.'];
    }
    if ($type === 'discount') {
        $pct = (int) ($data['reward_percent'] ?? 0);
        if ($pct < 1 || $pct > 90) {
            return [false, 'Discount must be 1–90%.'];
        }
        $value_minor = $pct; // stored as percent for discount prizes
    }
    if ($type === 'free_delivery' && $value_minor <= 0) {
        return [false, 'Free delivery needs a positive ₦ coupon value.'];
    }
    if ($type === 'promo_code') {
        if ($promo === '') {
            return [false, 'Promo prizes need a coupon code.'];
        }
        $stmt = db()->prepare('SELECT 1 FROM `coupons` WHERE `code` = ? AND `is_active` = 1');
        $stmt->execute([$promo]);
        if (!$stmt->fetchColumn()) {
            return [false, 'That coupon code does not exist or is inactive.'];
        }
    } else {
        $promo = '';
    }
    $weight = (int) ($data['probability_weight'] ?? 0);
    if ($weight < 0 || $weight > 1000000) {
        return [false, 'Weight must be 0–1000000.'];
    }
    $max_wins = trim((string) ($data['max_wins'] ?? ''));
    $max_wins = $max_wins === '' ? null : (int) $max_wins;
    if ($max_wins !== null && $max_wins < 1) {
        return [false, 'Max wins must be at least 1.'];
    }
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM `spin_prizes` WHERE `id` = ? AND `campaign_id` = ?');
        $stmt->execute([$id, $campaign_id]);
        $old = $stmt->fetch();
        if (!$old) {
            return [false, 'Prize not found.'];
        }
        if ($max_wins !== null && $max_wins < (int) $old['wins_count']) {
            return [false, 'Max wins cannot go below the ' . (int) $old['wins_count'] . ' already won.'];
        }
        db()->prepare(
            'UPDATE `spin_prizes` SET `label` = ?, `reward_type` = ?, `reward_value_minor` = ?, `promo_code` = ?,
             `probability_weight` = ?, `max_wins` = ?, `is_active` = ? WHERE `id` = ?'
        )->execute([$label, $type, $value_minor, $promo ?: null, $weight, $max_wins, $active, $id]);
        spin_audit('spin.prize_save', $actor_id, $id, null, ['label' => $label]);
        return [true, 'Prize saved.'];
    }
    db()->prepare(
        'INSERT INTO `spin_prizes` (`campaign_id`, `label`, `reward_type`, `reward_value_minor`, `promo_code`,
         `probability_weight`, `max_wins`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$campaign_id, $label, $type, $value_minor, $promo ?: null, $weight, $max_wins, $active]);
    spin_audit('spin.prize_create', $actor_id, (int) db()->lastInsertId(), null, ['label' => $label]);
    return [true, 'Prize created.'];
}

function spin_prize_delete($id, $campaign_id, $actor_id) {
    $stmt = db()->prepare('SELECT p.`label`, (SELECT COUNT(*) FROM `spins` s WHERE s.`prize_id` = p.`id`) AS used
        FROM `spin_prizes` p WHERE p.`id` = ? AND p.`campaign_id` = ?');
    $stmt->execute([(int) $id, (int) $campaign_id]);
    $row = $stmt->fetch();
    if (!$row) {
        return [false, 'Prize not found.'];
    }
    if ((int) $row['used'] > 0) {
        return [false, 'Cannot delete: this prize was already won. Deactivate it instead.'];
    }
    db()->prepare('DELETE FROM `spin_prizes` WHERE `id` = ?')->execute([(int) $id]);
    spin_audit('spin.prize_delete', $actor_id, (int) $id, ['label' => $row['label']], null);
    return [true, 'Prize deleted.'];
}

/* ---------------- eligibility (SP-05…SP-08) ---------------- */

function spin_order_volume($customer_id) {
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(`total_minor`), 0) FROM `orders`
         WHERE `customer_id` = ? AND `status` NOT IN ('cancelled','failed')"
    );
    $stmt->execute([(int) $customer_id]);
    return (int) $stmt->fetchColumn();
}

/** Returns [true, ''] or [false, $reason]. All rules enforced server-side. */
function spin_eligibility($campaign_id, $customer_id) {
    $c = spin_campaign_get((int) $campaign_id);
    if (!$c || !(int) $c['is_active']) {
        return [false, 'This campaign is not active.'];
    }
    $now = date('Y-m-d H:i:s');
    if ($c['starts_at'] > $now || $c['ends_at'] < $now) {
        return [false, 'This campaign is not running right now.'];
    }
    $pdo = db();
    if ($c['max_spins_per_user'] !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `spins` WHERE `campaign_id` = ? AND `customer_id` = ?');
        $stmt->execute([(int) $campaign_id, (int) $customer_id]);
        if ((int) $stmt->fetchColumn() >= (int) $c['max_spins_per_user']) {
            return [false, 'You have used all your spins for this campaign.'];
        }
    }
    $key = spin_period_key($c['period_rule']);
    $stmt = $pdo->prepare('SELECT 1 FROM `spins` WHERE `campaign_id` = ? AND `customer_id` = ? AND `period_key` = ?');
    $stmt->execute([(int) $campaign_id, (int) $customer_id, $key]);
    if ($stmt->fetchColumn()) {
        return [false, $c['period_rule'] === 'campaign' ? 'You already spun this campaign.'
            : ($c['period_rule'] === 'weekly' ? 'You already spun this week.' : 'You already spun today.')];
    }
    if ((int) $c['min_order_minor'] > 0 && spin_order_volume($customer_id) < (int) $c['min_order_minor']) {
        return [false, 'You need at least ' . format_money((int) $c['min_order_minor']) . ' in orders to spin.'];
    }
    return [true, ''];
}

/* ---------------- the draw (SP-13) ---------------- */

function spin_coupon_code() {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 5; $i++) {
        $code = 'SPN-';
        for ($j = 0; $j < 6; $j++) {
            $code .= $abc[random_int(0, strlen($abc) - 1)];
        }
        $stmt = db()->prepare('SELECT 1 FROM `coupons` WHERE `code` = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }
    return 'SPN-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

/**
 * Play one spin. Draws with random_int() inside a locked transaction,
 * then fulfils the reward. Returns [true, $spin] or [false, $error].
 */
function spin_play($campaign_id, $customer_id) {
    [$ok, $reason] = spin_eligibility($campaign_id, $customer_id);
    if (!$ok) {
        return [false, $reason];
    }
    $c = spin_campaign_get((int) $campaign_id);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $prizes = $pdo->query(
            'SELECT * FROM `spin_prizes` WHERE `campaign_id` = ' . (int) $campaign_id . ' FOR UPDATE'
        )->fetchAll();
        $pool = [];
        $total = 0;
        foreach ($prizes as $p) {
            if (!(int) $p['is_active'] || (int) $p['probability_weight'] <= 0) {
                continue;
            }
            if ($p['max_wins'] !== null && (int) $p['wins_count'] >= (int) $p['max_wins']) {
                continue;
            }
            $pool[] = $p;
            $total += (int) $p['probability_weight'];
        }
        if (!$pool || $total <= 0) {
            $pdo->rollBack();
            return [false, 'No prizes are available right now.'];
        }
        $roll = random_int(1, $total);
        $won = $pool[0];
        foreach ($pool as $p) {
            $roll -= (int) $p['probability_weight'];
            if ($roll <= 0) {
                $won = $p;
                break;
            }
        }
        $key = spin_period_key($c['period_rule']);
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO `spins` (`campaign_id`, `customer_id`, `prize_id`, `period_key`,
                 `reward_status`, `expires_at`, `ip_address`)
                 VALUES (?, ?, ?, ?, \'pending\', ?, ?)'
            );
            $stmt->execute([
                (int) $campaign_id, (int) $customer_id, (int) $won['id'], $key,
                $c['ends_at'], $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                return [false, 'You already used this spin.'];
            }
            throw $e;
        }
        $spin_id = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE `spin_prizes` SET `wins_count` = `wins_count` + 1 WHERE `id` = ?')
            ->execute([(int) $won['id']]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('spin_play: ' . $e->getMessage());
        return [false, 'Could not record your spin. Please try again.'];
    }

    // Fulfil outside the draw transaction (wallet lib manages its own).
    $type = $won['reward_type'];
    if ($type === 'none') {
        $pdo->prepare("UPDATE `spins` SET `reward_status` = 'credited' WHERE `id` = ?")->execute([$spin_id]);
    } elseif ($type === 'wallet_credit') {
        [$txn_id, $errs] = wallet_credit(
            (int) $customer_id, 'spin', (int) $won['reward_value_minor'],
            [
                'reference' => 'SPNCR-' . $spin_id . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
                'narration' => 'Spin reward: ' . $c['name'],
                'related_type' => 'spin', 'related_id' => $spin_id,
            ]
        );
        if ($txn_id) {
            $ref = $pdo->query('SELECT `reference` FROM `wallet_transactions` WHERE `id` = ' . (int) $txn_id)->fetchColumn();
            $pdo->prepare("UPDATE `spins` SET `reward_status` = 'credited', `wallet_txn_ref` = ? WHERE `id` = ?")
                ->execute([$ref, $spin_id]);
        } else {
            error_log('spin_play: deferred wallet credit for spin ' . $spin_id . ': ' . implode(';', $errs));
        }
    } elseif ($type === 'promo_code') {
        $pdo->prepare("UPDATE `spins` SET `reward_status` = 'credited', `reward_code` = ? WHERE `id` = ?")
            ->execute([$won['promo_code'], $spin_id]);
    } elseif ($type === 'discount' || $type === 'free_delivery') {
        $code = spin_coupon_code();
        $ctype = $type === 'discount' ? 'percent' : 'fixed';
        $cval = $type === 'discount' ? max(1, min(90, (int) $won['reward_value_minor'])) : (int) $won['reward_value_minor'];
        $pdo->prepare(
            'INSERT INTO `coupons` (`code`, `name`, `type`, `value`, `usage_limit`, `used_count`, `ends_at`, `is_active`)
             VALUES (?, ?, ?, ?, 1, 0, ?, 1)'
        )->execute([$code, 'Spin reward: ' . $won['label'], $ctype, $cval, $c['ends_at']]);
        $pdo->prepare("UPDATE `spins` SET `reward_status` = 'credited', `reward_code` = ? WHERE `id` = ?")
            ->execute([$code, $spin_id]);
    }
    spin_audit('spin.play', null, $spin_id, null, ['campaign' => $c['slug'], 'prize' => $won['label']]);
    $stmt = $pdo->prepare('SELECT s.*, p.`label`, p.`reward_type`, camp.`name` AS campaign_name
        FROM `spins` s JOIN `spin_prizes` p ON p.`id` = s.`prize_id`
        JOIN `spin_campaigns` camp ON camp.`id` = s.`campaign_id` WHERE s.`id` = ?');
    $stmt->execute([$spin_id]);
    return [true, $stmt->fetch()];
}

/** Retry a deferred (pending) wallet reward. */
function spin_claim($spin_id, $customer_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT s.*, p.`reward_type`, p.`reward_value_minor`, camp.`name` AS campaign_name
        FROM `spins` s JOIN `spin_prizes` p ON p.`id` = s.`prize_id`
        JOIN `spin_campaigns` camp ON camp.`id` = s.`campaign_id`
        WHERE s.`id` = ? AND s.`customer_id` = ?');
    $stmt->execute([(int) $spin_id, (int) $customer_id]);
    $s = $stmt->fetch();
    if (!$s) {
        return [false, 'Spin not found.'];
    }
    if ($s['reward_status'] !== 'pending') {
        return [false, 'Only pending rewards can be claimed.'];
    }
    if ($s['expires_at'] !== null && $s['expires_at'] < date('Y-m-d H:i:s')) {
        $pdo->prepare("UPDATE `spins` SET `reward_status` = 'expired' WHERE `id` = ?")->execute([(int) $spin_id]);
        return [false, 'This reward has expired.'];
    }
    if ($s['reward_type'] !== 'wallet_credit') {
        return [false, 'Only wallet rewards need claiming.'];
    }
    [$txn_id, $errs] = wallet_credit(
        (int) $customer_id, 'spin', (int) $s['reward_value_minor'],
        [
            'reference' => 'SPNCR-' . $spin_id . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'narration' => 'Spin reward: ' . $s['campaign_name'],
            'related_type' => 'spin', 'related_id' => (int) $spin_id,
        ]
    );
    if (!$txn_id) {
        return [false, implode(' ', $errs)];
    }
    $ref = $pdo->query('SELECT `reference` FROM `wallet_transactions` WHERE `id` = ' . (int) $txn_id)->fetchColumn();
    $pdo->prepare("UPDATE `spins` SET `reward_status` = 'credited', `wallet_txn_ref` = ? WHERE `id` = ?")
        ->execute([$ref, (int) $spin_id]);
    spin_audit('spin.claim', null, (int) $spin_id, ['status' => 'pending'], ['status' => 'credited']);
    return [true, 'Reward credited to your wallet.'];
}

/** Flip due pending rewards to expired (SP-11). Returns count. */
function spin_expire_due() {
    $stmt = db()->prepare(
        "UPDATE `spins` SET `reward_status` = 'expired'
         WHERE `reward_status` = 'pending' AND `expires_at` IS NOT NULL AND `expires_at` < NOW()"
    );
    $stmt->execute();
    return $stmt->rowCount();
}

/** Manual reversal by staff (SP-12). */
function spin_reverse($spin_id, $staff_id, $reason) {
    $reason = trim((string) $reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
        return [false, 'A reason of 5–255 characters is required.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT s.*, p.`reward_type` FROM `spins` s
        LEFT JOIN `spin_prizes` p ON p.`id` = s.`prize_id` WHERE s.`id` = ?');
    $stmt->execute([(int) $spin_id]);
    $s = $stmt->fetch();
    if (!$s) {
        return [false, 'Spin not found.'];
    }
    if ($s['reward_status'] !== 'credited') {
        return [false, 'Only credited rewards can be reversed.'];
    }
    if ($s['reward_type'] === 'wallet_credit' && !empty($s['wallet_txn_ref'])) {
        $stmt = $pdo->prepare('SELECT `id` FROM `wallet_transactions` WHERE `reference` = ?');
        $stmt->execute([$s['wallet_txn_ref']]);
        $txn_id = $stmt->fetchColumn();
        if ($txn_id) {
            [$ok, $msg] = wallet_reverse((int) $txn_id, (int) $staff_id, $reason);
            if (!$ok) {
                return [false, $msg];
            }
        }
    }
    if (in_array($s['reward_type'], ['discount', 'free_delivery'], true) && !empty($s['reward_code'])) {
        $pdo->prepare('UPDATE `coupons` SET `is_active` = 0 WHERE `code` = ?')->execute([$s['reward_code']]);
    }
    $pdo->prepare("UPDATE `spins` SET `reward_status` = 'reversed' WHERE `id` = ?")->execute([(int) $spin_id]);
    spin_audit('spin.reverse', $staff_id, (int) $spin_id, ['status' => 'credited'], ['status' => 'reversed']);
    $msg = 'Reward reversed.';
    if ($s['reward_type'] === 'promo_code') {
        $msg .= ' Note: the shared promo code itself stays active.';
    }
    return [true, $msg];
}

/* ---------------- history & reports (SP-09) ---------------- */

function spin_for_customer($customer_id, $limit = 50) {
    $stmt = db()->prepare(
        'SELECT s.*, p.`label`, p.`reward_type`, camp.`name` AS campaign_name
         FROM `spins` s JOIN `spin_prizes` p ON p.`id` = s.`prize_id`
         JOIN `spin_campaigns` camp ON camp.`id` = s.`campaign_id`
         WHERE s.`customer_id` = ? ORDER BY s.`id` DESC LIMIT ' . max(1, min(200, (int) $limit))
    );
    $stmt->execute([(int) $customer_id]);
    return $stmt->fetchAll();
}

function spin_all($campaign_id = 0, $status = '', $limit = 100) {
    $sql = 'SELECT s.*, p.`label`, p.`reward_type`, camp.`name` AS campaign_name, u.`name` AS customer_name
            FROM `spins` s JOIN `spin_prizes` p ON p.`id` = s.`prize_id`
            JOIN `spin_campaigns` camp ON camp.`id` = s.`campaign_id`
            JOIN `customers` c ON c.`id` = s.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if ((int) $campaign_id > 0) {
        $sql .= ' AND s.`campaign_id` = ?';
        $args[] = (int) $campaign_id;
    }
    if (in_array($status, ['pending', 'credited', 'expired', 'reversed'], true)) {
        $sql .= ' AND s.`reward_status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY s.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function spin_reports() {
    $pdo = db();
    return [
        'by_status' => $pdo->query('SELECT `reward_status`, COUNT(*) AS n FROM `spins` GROUP BY `reward_status`')->fetchAll(),
        'by_campaign' => $pdo->query('SELECT camp.`name`, COUNT(*) AS n FROM `spins` s JOIN `spin_campaigns` camp ON camp.`id` = s.`campaign_id` GROUP BY s.`campaign_id`')->fetchAll(),
        'wallet_paid' => $pdo->query(
            "SELECT COALESCE(SUM(t.`amount_minor`), 0) AS total FROM `wallet_transactions` t WHERE t.`type` = 'spin' AND t.`direction` = 'credit'"
        )->fetchColumn(),
    ];
}
