<?php
/**
 * Oyejo Gas - hardened wallet ledger library (Phase 11).
 *
 * Rules: money moves only here (and the Phase 9 checkout debit, which follows
 * the same pattern). Posted ledger rows are immutable (DB triggers); a
 * "reversal" is a NEW compensating entry, never an UPDATE. Every mutation is
 * locked (SELECT ... FOR UPDATE), validated against settings limits, and
 * audit-logged. References are idempotency keys (UNIQUE).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function wallet_defaults() {
    return [
        'wallet_topup_min_minor' => 100000,
        'wallet_topup_max_minor' => 50000000,
        'wallet_balance_cap_minor' => 200000000,
        'wallet_daily_topup_max_minor' => 100000000,
        'wallet_daily_topup_count' => 5,
    ];
}

/** Limits from settings with safe defaults. All values are ints. */
function wallet_limits() {
    $out = wallet_defaults();
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
    return $out;
}

function wallet_ref($prefix) {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $r = '';
    for ($i = 0; $i < 6; $i++) {
        $r .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $prefix . '-' . date('Ymd') . '-' . $r;
}

function wallet_audit($action, $user_id, $entity_id, $old, $new) {
    try {
        $ip = function_exists('auth_ip') ? auth_ip() : null;
        db()->prepare(
            'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)'
            . ' VALUES (?, ?, \'wallet\', ?, ?, ?, ?)'
        )->execute([
            $user_id ?: null, substr($action, 0, 100), $entity_id ? (int) $entity_id : null,
            $old !== null ? json_encode($old) : null, $new !== null ? json_encode($new) : null,
            $ip ? substr($ip, 0, 45) : null,
        ]);
    } catch (Throwable $t) {
        // Audit must not break money movement; error_log keeps a trace.
        error_log('[oyejo] wallet_audit failed: ' . $action);
    }
}

/** Fetch or lazily create the wallet row. Returns row or null. */
function wallet_ensure($customer_id) {
    $customer_id = (int) $customer_id;
    $s = db()->prepare('SELECT * FROM `wallets` WHERE `customer_id` = ? LIMIT 1');
    $s->execute([$customer_id]);
    $w = $s->fetch();
    if ($w) {
        return $w;
    }
    try {
        db()->prepare("INSERT INTO `wallets` (`customer_id`, `balance_minor`, `status`) VALUES (?, 0, 'active')")
            ->execute([$customer_id]);
    } catch (PDOException $e) {
        // Raced with another request; fall through to re-select.
    }
    $s->execute([$customer_id]);
    return $s->fetch() ?: null;
}

/** Balance derived purely from completed ledger rows. */
function wallet_derived($wallet_id) {
    $s = db()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN `direction` = 'credit' THEN `amount_minor` ELSE -`amount_minor` END), 0)"
        . " FROM `wallet_transactions` WHERE `wallet_id` = ? AND `status` = 'completed'"
    );
    $s->execute([(int) $wallet_id]);
    return (int) $s->fetchColumn();
}

/** [stored balance, derived balance, match?]. */
function wallet_check($wallet_id) {
    $s = db()->prepare('SELECT `balance_minor` FROM `wallets` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $wallet_id]);
    $bal = (int) $s->fetchColumn();
    $der = wallet_derived($wallet_id);
    return [$bal, $der, $bal === $der];
}

/**
 * Customer top-up request → PENDING credit. Staff approve/reject lands it.
 * Returns [row|null, errors].
 */
function wallet_request_topup($customer_id, $user_id, $amount_minor, $method, $note = '') {
    if (!oyejo_feature('customer_wallet')) {
        return [null, ['Wallet is currently disabled.']];
    }
    $w = wallet_ensure($customer_id);
    if (!$w) {
        return [null, ['Could not load your wallet.']];
    }
    if ($w['status'] !== 'active') {
        return [null, ['Your wallet is frozen. Contact support.']];
    }
    $amount = (int) $amount_minor;
    $lim = wallet_limits();
    if ($amount < $lim['wallet_topup_min_minor']) {
        return [null, ['Minimum top-up is ' . format_money($lim['wallet_topup_min_minor']) . '.']];
    }
    if ($amount > $lim['wallet_topup_max_minor']) {
        return [null, ['Maximum top-up is ' . format_money($lim['wallet_topup_max_minor']) . '.']];
    }
    if ((int) $w['balance_minor'] + $amount > $lim['wallet_balance_cap_minor']) {
        return [null, ['This would exceed the wallet cap of ' . format_money($lim['wallet_balance_cap_minor']) . '.']];
    }
    $s = db()->prepare(
        "SELECT COUNT(*) AS `c`, COALESCE(SUM(`amount_minor`), 0) AS `t` FROM `wallet_transactions`"
        . " WHERE `wallet_id` = ? AND `type` = 'topup' AND `status` IN ('pending','completed')"
        . ' AND DATE(`created_at`) = CURDATE()'
    );
    $s->execute([$w['id']]);
    $day = $s->fetch();
    if ((int) $day['c'] >= $lim['wallet_daily_topup_count']) {
        return [null, ['Daily top-up request limit reached. Try again tomorrow.']];
    }
    if ((int) $day['t'] + $amount > $lim['wallet_daily_topup_max_minor']) {
        return [null, ['Daily top-up amount limit reached.']];
    }
    $method = $method === 'online' ? 'online' : 'transfer';
    for ($i = 0; $i < 5; $i++) {
        try {
            $ref = wallet_ref('TOP');
            $narr = 'Top-up via ' . $method . ($note !== '' ? ' — ' . substr($note, 0, 150) : '');
            db()->prepare(
                'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
                . " `amount_minor`, `balance_after_minor`, `status`, `narration`, `meta`, `created_by`)"
                . " VALUES (?, ?, 'topup', 'credit', ?, ?, 'pending', ?, ?, ?)"
            )->execute([$w['id'], $ref, $amount, (int) $w['balance_minor'], substr($narr, 0, 255), json_encode(['method' => $method]), $user_id ?: null]);
            $id = (int) db()->lastInsertId();
            wallet_audit('wallet.topup.requested', $user_id, $id, null, ['reference' => $ref, 'amount' => $amount, 'method' => $method]);
            return [['id' => $id, 'reference' => $ref], []];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                continue; // reference collision: retry
            }
            error_log('[oyejo] topup request failed: ' . $e->getMessage());
            return [null, ['Could not record your request. Please try again.']];
        }
    }
    return [null, ['Could not record your request. Please try again.']];
}

/**
 * Staff decision on a pending top-up. Caller must check `payments.verify`.
 * Returns [ok, message].
 */
function wallet_decide_topup($txn_id, $staff_uid, $approve, $note = '') {
    try {
        db()->beginTransaction();
        $s = db()->prepare("SELECT * FROM `wallet_transactions` WHERE `id` = ? FOR UPDATE");
        $s->execute([(int) $txn_id]);
        $t = $s->fetch();
        if (!$t || $t['type'] !== 'topup' || $t['status'] !== 'pending') {
            throw new Exception('Top-up is no longer pending.');
        }
        $s = db()->prepare('SELECT * FROM `wallets` WHERE `id` = ? FOR UPDATE');
        $s->execute([$t['wallet_id']]);
        $w = $s->fetch();
        if (!$w) {
            throw new Exception('Wallet missing.');
        }
        if ($approve) {
            if ($w['status'] !== 'active') {
                throw new Exception('Wallet is frozen; unfreeze it first.');
            }
            $lim = wallet_limits();
            $new_bal = (int) $w['balance_minor'] + (int) $t['amount_minor'];
            if ($new_bal > $lim['wallet_balance_cap_minor']) {
                throw new Exception('Approval would exceed the wallet cap.');
            }
            db()->prepare("UPDATE `wallet_transactions` SET `status` = 'completed', `balance_after_minor` = ? WHERE `id` = ?")
                ->execute([$new_bal, $t['id']]);
            db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
                ->execute([$new_bal, $w['id']]);
            wallet_audit('wallet.topup.approved', $staff_uid, $t['id'], ['status' => 'pending'], ['status' => 'completed', 'balance_after' => $new_bal, 'note' => $note]);
        } else {
            db()->prepare("UPDATE `wallet_transactions` SET `status` = 'failed' WHERE `id` = ?")
                ->execute([$t['id']]);
            wallet_audit('wallet.topup.rejected', $staff_uid, $t['id'], ['status' => 'pending'], ['status' => 'failed', 'note' => $note]);
        }
        db()->commit();
        return [true, $approve ? 'Top-up approved.' : 'Top-up rejected.'];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[oyejo] topup decision failed: ' . $e->getMessage());
        return [false, 'Could not record the decision. Please try again.'];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [false, $e->getMessage()];
    }
}

/**
 * Completed credit primitive (promo / referral / spin / adjustment-credit).
 * Idempotent on `reference`: duplicates return the existing row. $args:
 * reference?, narration, related_type?, related_id?, created_by?, meta?
 * Returns [id|null, errors[], duplicate?].
 */
function wallet_credit($customer_id, $type, $amount_minor, array $args = []) {
    $allowed = ['promo', 'referral', 'spin', 'adjustment', 'refund'];
    if (!in_array($type, $allowed, true)) {
        return [null, ['Invalid credit type.'], false];
    }
    $amount = (int) $amount_minor;
    if ($amount <= 0) {
        return [null, ['Amount must be positive.'], false];
    }
    $w = wallet_ensure($customer_id);
    if (!$w) {
        return [null, ['Wallet missing.'], false];
    }
    if ($w['status'] !== 'active') {
        return [null, ['Wallet is frozen.'], false];
    }
    $lim = wallet_limits();
    if ((int) $w['balance_minor'] + $amount > $lim['wallet_balance_cap_minor']) {
        return [null, ['Credit would exceed the wallet cap.'], false];
    }
    $ref = (string) ($args['reference'] ?? wallet_ref('CR'));
    try {
        db()->beginTransaction();
        $s = db()->prepare('SELECT `id`, `balance_minor` FROM `wallets` WHERE `id` = ? FOR UPDATE');
        $s->execute([$w['id']]);
        $locked = $s->fetch();
        $new_bal = (int) $locked['balance_minor'] + $amount;
        if ($new_bal > $lim['wallet_balance_cap_minor']) {
            throw new Exception('Credit would exceed the wallet cap.');
        }
        db()->prepare(
            'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
            . " `amount_minor`, `balance_after_minor`, `status`, `related_type`, `related_id`,"
            . ' `narration`, `meta`, `created_by`)'
            . " VALUES (?, ?, ?, 'credit', ?, ?, 'completed', ?, ?, ?, ?, ?)"
        )->execute([
            $w['id'], $ref, $type, $amount, $new_bal,
            isset($args['related_type']) ? substr($args['related_type'], 0, 50) : null,
            isset($args['related_id']) ? (int) $args['related_id'] : null,
            isset($args['narration']) ? substr($args['narration'], 0, 255) : ('Credit: ' . $type),
            isset($args['meta']) ? substr($args['meta'], 0, 2000) : null,
            !empty($args['created_by']) ? (int) $args['created_by'] : null,
        ]);
        $id = (int) db()->lastInsertId();
        db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
            ->execute([$new_bal, $w['id']]);
        db()->commit();
        wallet_audit('wallet.credit', $args['created_by'] ?? null, $id, null, ['type' => $type, 'amount' => $amount, 'reference' => $ref]);
        return [$id, [], false];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($e->getCode() === '23000') {
            $s = db()->prepare('SELECT `id` FROM `wallet_transactions` WHERE `reference` = ? LIMIT 1');
            $s->execute([$ref]);
            $existing = $s->fetchColumn();
            if ($existing) {
                return [(int) $existing, [], true];
            }
        }
        error_log('[oyejo] wallet_credit failed: ' . $e->getMessage());
        return [null, ['Could not post the credit.'], false];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [null, [$e->getMessage()], false];
    }
}

/**
 * Staff adjustment (credit or debit) with mandatory reason. Caller must check
 * `wallet.adjust`. Balances never go negative. Returns [ok, message].
 */
function wallet_adjust($customer_id, $staff_uid, $direction, $amount_minor, $reason) {
    $direction = $direction === 'debit' ? 'debit' : 'credit';
    $amount = (int) $amount_minor;
    $reason = trim((string) $reason);
    if ($amount <= 0) {
        return [false, 'Amount must be positive.'];
    }
    if (strlen($reason) < 5 || strlen($reason) > 255) {
        return [false, 'A reason of 5–255 characters is required.'];
    }
    if ($direction === 'credit') {
        list($id, $errs) = wallet_credit($customer_id, 'adjustment', $amount, [
            'narration' => 'Adjustment: ' . $reason, 'created_by' => $staff_uid,
        ]);
        return $id ? [true, 'Adjustment credited.'] : [false, $errs[0] ?? 'Adjustment failed.'];
    }
    $w = wallet_ensure($customer_id);
    if (!$w || $w['status'] !== 'active') {
        return [false, 'Wallet missing or frozen.'];
    }
    try {
        db()->beginTransaction();
        $s = db()->prepare('SELECT `id`, `balance_minor` FROM `wallets` WHERE `id` = ? FOR UPDATE');
        $s->execute([$w['id']]);
        $locked = $s->fetch();
        if ((int) $locked['balance_minor'] < $amount) {
            throw new Exception('Adjustment exceeds the wallet balance.');
        }
        $new_bal = (int) $locked['balance_minor'] - $amount;
        db()->prepare(
            'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
            . " `amount_minor`, `balance_after_minor`, `status`, `narration`, `created_by`)"
            . " VALUES (?, ?, 'adjustment', 'debit', ?, ?, 'completed', ?, ?)"
        )->execute([$w['id'], wallet_ref('ADJ'), $amount, $new_bal, 'Adjustment: ' . substr($reason, 0, 240), $staff_uid]);
        $id = (int) db()->lastInsertId();
        db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
            ->execute([$new_bal, $w['id']]);
        db()->commit();
        wallet_audit('wallet.adjust', $staff_uid, $id, ['direction' => 'debit'], ['amount' => $amount, 'reason' => $reason]);
        return [true, 'Adjustment debited.'];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[oyejo] wallet_adjust failed: ' . $e->getMessage());
        return [false, 'Adjustment failed. Please try again.'];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [false, $e->getMessage()];
    }
}

/**
 * Reverse a COMPLETED entry via a compensating entry (original untouched).
 * Caller must check `wallet.adjust`. Returns [ok, message].
 */
function wallet_reverse($txn_id, $staff_uid, $reason) {
    $reason = trim((string) $reason);
    if (strlen($reason) < 5 || strlen($reason) > 255) {
        return [false, 'A reason of 5–255 characters is required.'];
    }
    try {
        db()->beginTransaction();
        $s = db()->prepare("SELECT * FROM `wallet_transactions` WHERE `id` = ? FOR UPDATE");
        $s->execute([(int) $txn_id]);
        $t = $s->fetch();
        if (!$t || $t['status'] !== 'completed') {
            throw new Exception('Only completed entries can be reversed.');
        }
        $s = db()->prepare('SELECT * FROM `wallets` WHERE `id` = ? FOR UPDATE');
        $s->execute([$t['wallet_id']]);
        $w = $s->fetch();
        if (!$w || $w['status'] !== 'active') {
            throw new Exception('Wallet missing or frozen.');
        }
        $dir = $t['direction'] === 'credit' ? 'debit' : 'credit';
        $new_bal = $dir === 'credit'
            ? (int) $w['balance_minor'] + (int) $t['amount_minor']
            : (int) $w['balance_minor'] - (int) $t['amount_minor'];
        if ($new_bal < 0) {
            throw new Exception('Reversal would drive the balance negative.');
        }
        $lim = wallet_limits();
        if ($new_bal > $lim['wallet_balance_cap_minor']) {
            throw new Exception('Reversal would exceed the wallet cap.');
        }
        db()->prepare(
            'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
            . " `amount_minor`, `balance_after_minor`, `status`, `related_type`, `related_id`,"
            . ' `narration`, `created_by`)'
            . " VALUES (?, ?, 'reversal', ?, ?, ?, 'completed', 'wallet_txn', ?, ?, ?)"
        )->execute([
            $w['id'], wallet_ref('RV'), $dir, (int) $t['amount_minor'], $new_bal,
            $t['id'], 'Reversal of ' . $t['reference'] . ': ' . substr($reason, 0, 200), $staff_uid,
        ]);
        $id = (int) db()->lastInsertId();
        db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
            ->execute([$new_bal, $w['id']]);
        db()->commit();
        wallet_audit('wallet.reverse', $staff_uid, $id, ['reverses' => $t['reference']], ['amount' => $t['amount_minor'], 'reason' => $reason]);
        return [true, 'Entry reversed with a compensating transaction.'];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[oyejo] wallet_reverse failed: ' . $e->getMessage());
        return [false, 'Reversal failed. Please try again.'];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [false, $e->getMessage()];
    }
}

/** Freeze/unfreeze a wallet. Caller must check `wallet.adjust`. */
function wallet_freeze($customer_id, $staff_uid, $freeze, $reason = '') {
    $to = $freeze ? 'frozen' : 'active';
    $s = db()->prepare('SELECT `id`, `status` FROM `wallets` WHERE `customer_id` = ? LIMIT 1');
    $s->execute([(int) $customer_id]);
    $w = $s->fetch();
    if (!$w) {
        return [false, 'Wallet not found.'];
    }
    if ($w['status'] === $to) {
        return [true, 'Wallet already ' . $to . '.'];
    }
    db()->prepare('UPDATE `wallets` SET `status` = ? WHERE `id` = ?')->execute([$to, $w['id']]);
    wallet_audit('wallet.freeze', $staff_uid, $w['id'], ['status' => $w['status']], ['status' => $to, 'reason' => substr($reason, 0, 255)]);
    return [true, 'Wallet ' . $to . '.'];
}

/** Filtered history for the wallet page. */
function wallet_history($wallet_id, $type = '', $status = '', $limit = 100) {
    $where = ['`wallet_id` = ?'];
    $args = [(int) $wallet_id];
    $types = ['topup', 'payment', 'refund', 'promo', 'referral', 'spin', 'adjustment', 'reversal'];
    $statuses = ['pending', 'completed', 'failed', 'reversed'];
    if (in_array($type, $types, true)) {
        $where[] = '`type` = ?';
        $args[] = $type;
    }
    if (in_array($status, $statuses, true)) {
        $where[] = '`status` = ?';
        $args[] = $status;
    }
    $s = db()->prepare('SELECT * FROM `wallet_transactions` WHERE ' . implode(' AND ', $where) . ' ORDER BY `id` DESC LIMIT ' . (int) $limit);
    $s->execute($args);
    return $s->fetchAll();
}

/** Parse a naira amount string to minor units without float math. Null if bad. */
function wallet_parse_amount($raw) {
    $raw = trim((string) $raw);
    if (!preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $raw, $m)) {
        return null;
    }
    $minor = (int) $m[1] * 100;
    if (isset($m[2])) {
        $minor += (int) str_pad($m[2], 2, '0');
    }
    return $minor > 0 ? $minor : null;
}
