<?php
/**
 * Oyejo Gas - payments, invoices, refunds and reconciliation (Phase 17).
 * Gateway secrets are NEVER stored in the database or web files: they live
 * in server environment only (see pay_gateway()).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function pay_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'payment', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Config-driven gateway info. Secrets stay in environment (PY-15). */
function pay_gateways() {
    $out = [];
    $ps = trim((string) env('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: ''));
    if ($ps === '') {
        $ps = trim((string) env('GATEWAY_SECRET_KEY', getenv('GATEWAY_SECRET_KEY') ?: ''));
    }
    if ($ps !== '') {
        $out['paystack'] = ['label' => 'Paystack', 'public' => (string) env('PAYSTACK_PUBLIC_KEY', '')];
    }
    $opay_prv = trim((string) env('OPAY_PRIVATE_KEY', getenv('OPAY_PRIVATE_KEY') ?: ''));
    $opay_mid = trim((string) env('OPAY_MERCHANT_ID', getenv('OPAY_MERCHANT_ID') ?: ''));
    if ($opay_prv !== '' && $opay_mid !== '') {
        $out['opay'] = ['label' => 'Opay'];
    }
    return $out;
}

function pay_gateway() {
    $all = pay_gateways();
    $pref = strtolower(trim((string) env('ONLINE_GATEWAY', getenv('ONLINE_GATEWAY') ?: '')));
    if ($pref !== '' && isset($all[$pref])) {
        return ['name' => $pref, 'configured' => true];
    }
    $first = array_key_first($all);
    return ['name' => $first ?: 'paystack', 'configured' => $first !== null];
}

function pay_unique_ref($pdo, $table, $column, $prefix) {
    for ($i = 0; $i < 5; $i++) {
        $n = $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ?");
        $stmt->execute([$n]);
        if (!$stmt->fetchColumn()) {
            return $n;
        }
    }
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
}

/**
 * Checkout hook: run INSIDE the cart transaction. Creates the payments row
 * (unique reference) and the persistent invoice for the new order.
 */
function pay_record_checkout($order_id, $customer_id, $method, $total_minor, $wallet_txn_id = 0) {
    $pdo = db();
    $gw = pay_gateway();
    $ref = pay_unique_ref($pdo, 'payments', 'payment_reference', 'PAY');
    $status = $method === 'wallet' ? 'verified' : 'pending';
    $stmt = $pdo->prepare(
        'INSERT INTO `payments` (`payment_reference`, `order_id`, `customer_id`, `wallet_txn_id`,
         `method`, `gateway`, `amount_minor`, `status`, `verified_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ' . ($status === 'verified' ? 'NOW()' : 'NULL') . ')'
    );
    $stmt->execute([$ref, (int) $order_id, (int) $customer_id, $wallet_txn_id ? (int) $wallet_txn_id : null,
        $method, $method === 'online' ? $gw['name'] : null, (int) $total_minor, $status]);
    $inv = pay_unique_ref($pdo, 'invoices', 'invoice_number', 'INV');
    $stmt = $pdo->prepare(
        "INSERT INTO `invoices` (`invoice_number`, `order_id`, `status`) VALUES (?, ?, ?)"
    );
    $stmt->execute([$inv, (int) $order_id, $status === 'verified' ? 'paid' : 'issued']);
    return $ref;
}

/** Keep invoice status in sync with its order. */
function pay_invoice_sync($order_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT `payment_status`, `status` FROM `orders` WHERE `id` = ?');
    $stmt->execute([(int) $order_id]);
    $o = $stmt->fetch();
    if (!$o) {
        return;
    }
    $to = $o['status'] === 'cancelled' ? 'void' : ($o['payment_status'] === 'paid' ? 'paid' : 'issued');
    $pdo->prepare('UPDATE `invoices` SET `status` = ? WHERE `order_id` = ?')->execute([$to, (int) $order_id]);
}

function pay_invoice_for_order($order_id) {
    $stmt = db()->prepare('SELECT * FROM `invoices` WHERE `order_id` = ? ORDER BY `id` DESC LIMIT 1');
    $stmt->execute([(int) $order_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pay_for_customer($customer_id, $limit = 50) {
    $stmt = db()->prepare(
        'SELECT p.*, o.`order_number`, o.`status` AS order_status
         FROM `payments` p LEFT JOIN `orders` o ON o.`id` = p.`order_id`
         WHERE p.`customer_id` = ? ORDER BY p.`id` DESC LIMIT ' . max(1, min(200, (int) $limit))
    );
    $stmt->execute([(int) $customer_id]);
    return $stmt->fetchAll();
}

function pay_list($status = '', $method = '', $limit = 100) {
    $sql = 'SELECT p.*, o.`order_number`, u.`name` AS customer_name
            FROM `payments` p LEFT JOIN `orders` o ON o.`id` = p.`order_id`
            JOIN `customers` c ON c.`id` = p.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'verified', 'failed', 'refunded', 'partially_refunded'], true)) {
        $sql .= ' AND p.`status` = ?';
        $args[] = $status;
    }
    if (in_array($method, ['wallet', 'cod', 'transfer', 'online'], true)) {
        $sql .= ' AND p.`method` = ?';
        $args[] = $method;
    }
    $sql .= ' ORDER BY p.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function pay_get($id) {
    $stmt = db()->prepare(
        'SELECT p.*, o.`order_number`, o.`status` AS order_status, o.`total_minor` AS order_total,
                u.`name` AS customer_name, u.`email` AS customer_email, u.`phone` AS customer_phone,
                u.`whatsapp` AS customer_whatsapp
         FROM `payments` p LEFT JOIN `orders` o ON o.`id` = p.`order_id`
         JOIN `customers` c ON c.`id` = p.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id` WHERE p.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pay_notify($payment, $subject, $body) {
    try {
        notify_emit((int) $payment['customer_id'], 'payment_update',
            (string) ($payment['customer_email'] ?? ''), $subject, $body, 'email');
    } catch (Throwable $e) {
        report_error('payments', 'error', $e);
    }
}

/** Transfer proof upload (PY-02). JPG/PNG ≤ 2 MB into uploads/payment-proof/. */
function pay_upload_proof($payment_id, $customer_id, $file) {
    $stmt = db()->prepare('SELECT * FROM `payments` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $payment_id, (int) $customer_id]);
    $p = $stmt->fetch();
    if (!$p) {
        return [false, 'Payment not found.'];
    }
    if ($p['method'] !== 'transfer' || $p['status'] !== 'pending') {
        return [false, 'Proof can only be attached to a pending transfer.'];
    }
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Choose a receipt image to upload.'];
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        return [false, 'Receipt must be 2 MB or smaller.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return [false, 'Only JPG or PNG receipts are accepted.'];
    }
    $dir = BASE_PATH . '/uploads/payment-proof';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [false, 'Could not store the receipt.'];
    }
    $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
    $name = 'PROOF-' . (int) $payment_id . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        report_error('files', 'error', 'Receipt upload failed for payment ' . (int) $payment_id);
        return [false, 'Could not store the receipt.'];
    }
    db()->prepare('UPDATE `payments` SET `proof_image` = ? WHERE `id` = ?')
        ->execute(['payment-proof/' . $name, (int) $payment_id]);
    pay_audit('payment.proof', null, (int) $payment_id, null, ['file' => $name]);
    return [true, 'Receipt attached — our team will verify your transfer.'];
}

/** Customer confirms an online gateway payment completed (stores gateway ref). */
function pay_online_paid($payment_id, $customer_id, $gateway_ref) {
    $gateway_ref = trim((string) $gateway_ref);
    if ($gateway_ref === '' || mb_strlen($gateway_ref) > 100) {
        return [false, 'Paste the reference from your gateway receipt.'];
    }
    $stmt = db()->prepare('SELECT * FROM `payments` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $payment_id, (int) $customer_id]);
    $p = $stmt->fetch();
    if (!$p) {
        return [false, 'Payment not found.'];
    }
    if ($p['method'] !== 'online' || $p['status'] !== 'pending') {
        return [false, 'Only a pending online payment can be confirmed.'];
    }
    db()->prepare('UPDATE `payments` SET `gateway_ref` = ? WHERE `id` = ?')
        ->execute([mb_substr($gateway_ref, 0, 100), (int) $payment_id]);
    pay_audit('payment.gateway_ref', null, (int) $payment_id, null, ['ref' => $gateway_ref]);
    return [true, 'Reference saved — verification follows automatically once the gateway confirms.'];
}

/** Re-attempt a failed payment (PY-14). */
function pay_retry($payment_id, $customer_id) {
    $stmt = db()->prepare('SELECT * FROM `payments` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $payment_id, (int) $customer_id]);
    $p = $stmt->fetch();
    if (!$p) {
        return [false, 'Payment not found.'];
    }
    if ($p['status'] !== 'failed') {
        return [false, 'Only failed payments can be retried.'];
    }
    db()->prepare("UPDATE `payments` SET `status` = 'pending', `gateway_ref` = NULL WHERE `id` = ?")
        ->execute([(int) $payment_id]);
    pay_audit('payment.retry', null, (int) $payment_id, ['status' => 'failed'], ['status' => 'pending']);
    return [true, 'Payment re-queued — complete it again.'];
}

function pay_apply_verified($pdo, $p, $actor_id) {
    $pdo->prepare("UPDATE `payments` SET `status` = 'verified', `verified_by` = ?, `verified_at` = NOW() WHERE `id` = ?")
        ->execute([$actor_id ?: null, (int) $p['id']]);
    if ($p['order_id']) {
        $pdo->prepare("UPDATE `orders` SET `payment_status` = 'paid' WHERE `id` = ?")->execute([(int) $p['order_id']]);
        pay_invoice_sync((int) $p['order_id']);
    }
}

/** Staff verification decision (PY-04). */
function pay_verify($id, $approve, $actor_id, $note = '') {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `payments` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $p = $stmt->fetch();
        if (!$p) {
            $pdo->rollBack();
            return [false, 'Payment not found.'];
        }
        if ($p['status'] !== 'pending') {
            $pdo->rollBack();
            return [false, 'Only pending payments can be decided.'];
        }
        if ($approve) {
            pay_apply_verified($pdo, $p, $actor_id);
        } else {
            $note = mb_substr(trim((string) $note), 0, 255);
            $pdo->prepare("UPDATE `payments` SET `status` = 'failed', `verified_by` = ?, `verified_at` = NOW() WHERE `id` = ?")
                ->execute([$actor_id ?: null, (int) $id]);
        }
        pay_audit('payment.verify', $actor_id, (int) $id, ['status' => 'pending'],
            ['status' => $approve ? 'verified' : 'failed', 'note' => $note]);
        $pdo->commit();
        $full = pay_get((int) $id);
        if ($full) {
            $label = $full['order_number'] ? 'Order ' . $full['order_number'] : $p['payment_reference'];
            pay_notify($full, $approve ? 'Payment confirmed for ' . $label : 'Payment failed for ' . $label,
                $approve ? 'Your payment of ₦' . number_format((int) $p['amount_minor'] / 100, 2) . ' for ' . $label . ' is confirmed.'
                    : 'Your payment for ' . $label . ' could not be verified' . ($note !== '' ? ': ' . $note : '') . '. You can retry from My payments.');
        }
        return [true, $p['payment_reference'] . ($approve ? ' verified — order marked paid.' : ' marked failed.')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        report_error('payments', 'error', $e);
        return [false, 'Could not decide the payment. Please try again.'];
    }
}

/** Fail a stuck pending payment (expired gateway attempt). */
function pay_mark_failed($id, $actor_id, $reason = '') {
    return pay_verify($id, false, $actor_id, $reason === '' ? 'Marked failed by finance' : $reason);
}

/** Auto-verify a COD payment once collected cash covers the order (PY-01). */
function pay_cod_autoverify($order_id) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT * FROM `payments` WHERE `order_id` = ? AND `method` = 'cod' AND `status` = 'pending' LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([(int) $order_id]);
        $p = $stmt->fetch();
        if (!$p) {
            $pdo->rollBack();
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(`cash_collected_minor`), 0) FROM `deliveries` WHERE `order_id` = ?'
        );
        $stmt->execute([(int) $order_id]);
        if ((int) $stmt->fetchColumn() < (int) $p['amount_minor']) {
            $pdo->rollBack();
            return;
        }
        pay_apply_verified($pdo, $p, null);
        pay_audit('payment.cod_auto', null, (int) $p['id'], ['status' => 'pending'], ['status' => 'verified']);
        $pdo->commit();
        $full = pay_get((int) $p['id']);
        if ($full) {
            pay_notify($full, 'Cash received for Order ' . ($full['order_number'] ?? ''),
                'Your cash payment of ₦' . number_format((int) $p['amount_minor'] / 100, 2) . ' was received. Thank you!');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('pay_cod_autoverify: ' . $e->getMessage());
    }
}

/* ---------------- refunds (PY-09…PY-11) ---------------- */

function pay_refunded_minor($payment_id) {
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(`amount_minor`), 0) FROM `refunds`
         WHERE `payment_id` = ? AND `status` IN ('approved','completed')"
    );
    $stmt->execute([(int) $payment_id]);
    return (int) $stmt->fetchColumn();
}

/** $actor: ['customer_id'=>int|null,'user_id'=>int|null,'is_staff'=>bool]. */
function ref_request($order_id, $naira, $reason, $method, $actor) {
    $minor = (int) round((float) $naira * 100);
    $reason = mb_substr(trim((string) $reason), 0, 255);
    if ($minor < 1 || $minor > 100000000) {
        return [false, 'Refund amount looks invalid.'];
    }
    if ($reason === '') {
        return [false, 'A reason is required.'];
    }
    if (!in_array($method, ['wallet', 'bank', 'cash'], true)) {
        return [false, 'Unknown refund method.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE `id` = ?');
    $stmt->execute([(int) $order_id]);
    $o = $stmt->fetch();
    if (!$o) {
        return [false, 'Order not found.'];
    }
    if (empty($actor['is_staff']) && (int) $o['customer_id'] !== (int) $actor['customer_id']) {
        return [false, 'That is not your order.'];
    }
    if ($o['payment_status'] !== 'paid') {
        return [false, 'Only paid orders can be refunded.'];
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM `payments` WHERE `order_id` = ? AND `status` IN ('verified','partially_refunded')
         ORDER BY `id` DESC LIMIT 1"
    );
    $stmt->execute([(int) $order_id]);
    $p = $stmt->fetch();
    if (!$p) {
        return [false, 'No refundable payment found for that order.'];
    }
    $left = (int) $p['amount_minor'] - pay_refunded_minor((int) $p['id']);
    if ($minor > $left) {
        return [false, 'Only ₦' . number_format($left / 100, 2) . ' is still refundable.'];
    }
    $stmt = $pdo->prepare(
        "SELECT 1 FROM `refunds` WHERE `payment_id` = ? AND `status` = 'pending' LIMIT 1"
    );
    $stmt->execute([(int) $p['id']]);
    if ($stmt->fetchColumn()) {
        return [false, 'A refund request is already waiting on that payment.'];
    }
    $num = pay_unique_ref($pdo, 'refunds', 'refund_number', 'RND');
    $pdo->prepare(
        'INSERT INTO `refunds` (`refund_number`, `payment_id`, `order_id`, `amount_minor`, `reason`, `method`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, \'pending\')'
    )->execute([$num, (int) $p['id'], (int) $order_id, $minor, $reason, $method]);
    pay_audit('refund.request', $actor['user_id'] ?? null, (int) $pdo->lastInsertId(), null,
        ['number' => $num, 'amount_minor' => $minor, 'method' => $method]);
    return [true, 'Refund ' . $num . ' requested (₦' . number_format($minor / 100, 2) . ').'];
}

function ref_list($status = '', $limit = 100) {
    $sql = 'SELECT r.*, p.`payment_reference`, p.`method` AS pay_method, o.`order_number`, u.`name` AS customer_name
            FROM `refunds` r JOIN `payments` p ON p.`id` = r.`payment_id`
            JOIN `orders` o ON o.`id` = r.`order_id`
            JOIN `customers` c ON c.`id` = o.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'approved', 'completed', 'rejected'], true)) {
        $sql .= ' AND r.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY r.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function ref_get($id) {
    $stmt = db()->prepare(
        'SELECT r.*, p.`payment_reference`, p.`method` AS pay_method, p.`amount_minor` AS pay_amount,
                p.`customer_id`, o.`order_number`, u.`email` AS customer_email
         FROM `refunds` r JOIN `payments` p ON p.`id` = r.`payment_id`
         JOIN `orders` o ON o.`id` = r.`order_id`
         JOIN `customers` c ON c.`id` = o.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id` WHERE r.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ref_sync_payment($pdo, $payment_id) {
    $stmt = $pdo->prepare('SELECT `amount_minor` FROM `payments` WHERE `id` = ?');
    $stmt->execute([(int) $payment_id]);
    $total = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(`amount_minor`), 0) FROM `refunds`
         WHERE `payment_id` = ? AND `status` IN ('approved','completed')"
    );
    $stmt->execute([(int) $payment_id]);
    $done = (int) $stmt->fetchColumn();
    $to = $done >= $total ? 'refunded' : ($done > 0 ? 'partially_refunded' : 'verified');
    $pdo->prepare('UPDATE `payments` SET `status` = ? WHERE `id` = ?')->execute([$to, (int) $payment_id]);
}

/**
 * Approve (wallet refunds complete instantly; bank/cash move to approved
 * awaiting manual payout) or reject a refund request.
 */
function ref_decide($id, $approve, $actor_id) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `refunds` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $r = $stmt->fetch();
        if (!$r) {
            $pdo->rollBack();
            return [false, 'Refund not found.'];
        }
        if ($r['status'] !== 'pending') {
            $pdo->rollBack();
            return [false, 'Only pending refunds can be decided.'];
        }
        if (!$approve) {
            $pdo->prepare("UPDATE `refunds` SET `status` = 'rejected', `processed_by` = ?, `processed_at` = NOW() WHERE `id` = ?")
                ->execute([$actor_id ?: null, (int) $id]);
            pay_audit('refund.reject', $actor_id, (int) $id, ['status' => 'pending'], ['status' => 'rejected']);
            $pdo->commit();
            $full = ref_get((int) $id);
            if ($full) {
                pay_notify(['customer_id' => $full['customer_id'], 'customer_email' => $full['customer_email']],
                    'Refund ' . $r['refund_number'] . ' declined',
                    'Your refund request for order ' . $full['order_number'] . ' was declined. Contact support for help.');
            }
            return [true, $r['refund_number'] . ' rejected.'];
        }
        if ($r['method'] === 'wallet') {
            // Commit the decision first, then post the wallet credit (own txn).
            $pdo->prepare("UPDATE `refunds` SET `status` = 'completed', `processed_by` = ?, `processed_at` = NOW() WHERE `id` = ?")
                ->execute([$actor_id ?: null, (int) $id]);
            ref_sync_payment($pdo, (int) $r['payment_id']);
            pay_audit('refund.approve', $actor_id, (int) $id, ['status' => 'pending'], ['status' => 'completed']);
            $pdo->commit();
            $stmt = $pdo->prepare('SELECT `customer_id` FROM `orders` WHERE `id` = ?');
            $stmt->execute([(int) $r['order_id']]);
            $cid = (int) $stmt->fetchColumn();
            [$wid, $errs] = wallet_credit($cid, 'refund', (int) $r['amount_minor'], [
                'reference' => 'RND-' . $r['refund_number'],
                'narration' => 'Refund ' . $r['refund_number'] . ' for order #' . (int) $r['order_id'],
                'related_type' => 'refund', 'related_id' => (int) $id, 'created_by' => $actor_id ?: null,
            ]);
            if (!$wid) {
                error_log('ref_decide wallet credit failed: ' . implode(' ', $errs));
                return [false, 'Refund approved but the wallet credit failed: ' . implode(' ', $errs)];
            }
            $full = ref_get((int) $id);
            if ($full) {
                pay_notify(['customer_id' => $full['customer_id'], 'customer_email' => $full['customer_email']],
                    'Refund ' . $r['refund_number'] . ' paid to wallet',
                    '₦' . number_format((int) $r['amount_minor'] / 100, 2) . ' was refunded to your wallet for order ' . $full['order_number'] . '.');
            }
            return [true, $r['refund_number'] . ' completed — wallet credited.'];
        }
        $pdo->prepare("UPDATE `refunds` SET `status` = 'approved', `processed_by` = ?, `processed_at` = NOW() WHERE `id` = ?")
            ->execute([$actor_id ?: null, (int) $id]);
        ref_sync_payment($pdo, (int) $r['payment_id']);
        pay_audit('refund.approve', $actor_id, (int) $id, ['status' => 'pending'], ['status' => 'approved']);
        $pdo->commit();
        return [true, $r['refund_number'] . ' approved — complete it after the manual payout.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('ref_decide: ' . $e->getMessage());
        return [false, 'Could not decide the refund. Please try again.'];
    }
}

/** Mark an approved bank/cash refund completed after the manual payout. */
function ref_complete($id, $actor_id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM `refunds` WHERE `id` = ? AND `status` = 'approved'");
    $stmt->execute([(int) $id]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'Only approved refunds can be completed.'];
    }
    $pdo->prepare("UPDATE `refunds` SET `status` = 'completed' WHERE `id` = ?")->execute([(int) $id]);
    ref_sync_payment($pdo, (int) $r['payment_id']);
    pay_audit('refund.complete', $actor_id, (int) $id, ['status' => 'approved'], ['status' => 'completed']);
    return [true, $r['refund_number'] . ' marked completed.'];
}

/* ---------------- driver cash reconciliation (PY-12) ---------------- */

function recon_pending() {
    return db()->query(
        "SELECT cc.*, d.`delivery_number`, d.`cash_expected_minor`, dr.`driver_code`, u.`name` AS driver_name
         FROM `driver_cash_collections` cc
         JOIN `deliveries` d ON d.`id` = cc.`delivery_id`
         JOIN `drivers` dr ON dr.`id` = cc.`driver_id`
         JOIN `users` u ON u.`id` = dr.`user_id`
         WHERE cc.`reconciled` = 0 ORDER BY cc.`id` DESC LIMIT 200"
    )->fetchAll();
}

function recon_mark($collection_id, $actor_id) {
    $stmt = db()->prepare('SELECT * FROM `driver_cash_collections` WHERE `id` = ?');
    $stmt->execute([(int) $collection_id]);
    $c = $stmt->fetch();
    if (!$c) {
        return [false, 'Collection not found.'];
    }
    if ((int) $c['reconciled']) {
        return [false, 'Already reconciled.'];
    }
    db()->prepare('UPDATE `driver_cash_collections` SET `reconciled` = 1, `reconciled_at` = NOW(), `reconciled_by` = ? WHERE `id` = ?')
        ->execute([$actor_id ?: null, (int) $collection_id]);
    pay_audit('cash.reconcile', $actor_id, (int) $collection_id, null, ['amount_minor' => (int) $c['amount_minor']]);
    return [true, '₦' . number_format((int) $c['amount_minor'] / 100, 2) . ' reconciled.'];
}

/* ---------------- finance reports (PY-13) ---------------- */

function pay_by_reference($ref) {
    $stmt = db()->prepare(
        'SELECT p.*, o.`order_number`, o.`status` AS order_status, o.`total_minor` AS order_total,
                u.`name` AS customer_name, u.`email` AS customer_email, u.`phone` AS customer_phone,
                u.`whatsapp` AS customer_whatsapp
         FROM `payments` p LEFT JOIN `orders` o ON o.`id` = p.`order_id`
         JOIN `customers` c ON c.`id` = p.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id` WHERE p.`payment_reference` = ? LIMIT 1'
    );
    $stmt->execute([(string) $ref]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pay_http($url, $method, array $headers, $body = null) {
    if (!function_exists('curl_init')) {
        return [false, 'curl unavailable', null];
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [false, 'gateway unreachable: ' . $err, null];
    }
    $data = json_decode((string) $resp, true);
    return [$code >= 200 && $code < 300, (string) $resp, is_array($data) ? $data : null];
}

function pay_mark_gateway($payment_id, $gateway, $gateway_ref = '') {
    db()->prepare('UPDATE `payments` SET `gateway` = ?, `gateway_ref` = COALESCE(NULLIF(?, \'\'), `gateway_ref`) WHERE `id` = ?')
        ->execute([$gateway, mb_substr((string) $gateway_ref, 0, 100), (int) $payment_id]);
}

/** Returns [ok, message, redirect_url|null]. */
function pay_paystack_init(array $p) {
    $secret = trim((string) env('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: ''));
    if ($secret === '') {
        $secret = trim((string) env('GATEWAY_SECRET_KEY', ''));
    }
    if ($secret === '') {
        return [false, 'Paystack is not configured.', null];
    }
    $email = (string) ($p['customer_email'] ?? '');
    if ($email === '') {
        $full = pay_get((int) $p['id']);
        $email = (string) ($full['customer_email'] ?? '');
        $p = $full ?: $p;
    }
    if ($email === '') {
        return [false, 'Your account needs an email for Paystack.', null];
    }
    $callback = url('customer/pay-return.php?gateway=paystack');
    [$ok, $raw, $data] = pay_http('https://api.paystack.co/transaction/initialize', 'POST', [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
    ], [
        'email' => $email,
        'amount' => (int) $p['amount_minor'],
        'reference' => $p['payment_reference'],
        'callback_url' => $callback,
        'metadata' => ['payment_id' => (int) $p['id'], 'order_id' => (int) ($p['order_id'] ?? 0)],
    ]);
    if (!$ok || empty($data['status']) || empty($data['data']['authorization_url'])) {
        $msg = is_array($data) ? (string) ($data['message'] ?? 'Paystack initialize failed') : 'Paystack initialize failed';
        return [false, $msg, null];
    }
    pay_mark_gateway((int) $p['id'], 'paystack', (string) ($data['data']['reference'] ?? $p['payment_reference']));
    return [true, 'Redirecting to Paystack.', (string) $data['data']['authorization_url']];
}

function pay_paystack_verify(array $p) {
    $secret = trim((string) env('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: ''));
    if ($secret === '') {
        $secret = trim((string) env('GATEWAY_SECRET_KEY', ''));
    }
    if ($secret === '') {
        return [false, 'Paystack is not configured.'];
    }
    $ref = rawurlencode((string) $p['payment_reference']);
    [$ok, $raw, $data] = pay_http('https://api.paystack.co/transaction/verify/' . $ref, 'GET', [
        'Authorization: Bearer ' . $secret,
    ]);
    if (!$ok || empty($data['status'])) {
        return [false, 'Could not verify with Paystack yet. Try again shortly.'];
    }
    $st = strtolower((string) ($data['data']['status'] ?? ''));
    $paid = (int) ($data['data']['amount'] ?? 0);
    if ($st === 'success' && $paid >= (int) $p['amount_minor']) {
        $gref = (string) ($data['data']['reference'] ?? $p['payment_reference']);
        pay_mark_gateway((int) $p['id'], 'paystack', $gref);
        return pay_verify((int) $p['id'], true, null, 'paystack verify');
    }
    return [false, 'Paystack has not confirmed this payment yet.'];
}

function pay_opay_base() {
    $u = trim((string) env('OPAY_BASE_URL', getenv('OPAY_BASE_URL') ?: ''));
    return rtrim($u !== '' ? $u : 'https://liveapi.opaycheckout.com', '/');
}

function pay_opay_sign($json, $private) {
    return hash_hmac('sha512', $json, $private);
}

function pay_opay_init(array $p) {
    $prv = trim((string) env('OPAY_PRIVATE_KEY', getenv('OPAY_PRIVATE_KEY') ?: ''));
    $mid = trim((string) env('OPAY_MERCHANT_ID', getenv('OPAY_MERCHANT_ID') ?: ''));
    if ($prv === '' || $mid === '') {
        return [false, 'Opay is not configured.', null];
    }
    $full = pay_get((int) $p['id']) ?: $p;
    $payload = [
        'country' => 'NG',
        'reference' => (string) $p['payment_reference'],
        'amount' => ['total' => (int) $p['amount_minor'], 'currency' => 'NGN'],
        'returnUrl' => url('customer/pay-return.php?gateway=opay&reference=' . rawurlencode((string) $p['payment_reference'])),
        'callbackUrl' => url('api/payments-callback.php?gateway=opay'),
        'cancelUrl' => url('customer/payments.php'),
        'expireAt' => '30',
        'userInfo' => [
            'userEmail' => (string) ($full['customer_email'] ?? ''),
            'userId' => (string) (int) $p['customer_id'],
            'userMobile' => (string) ($full['customer_phone'] ?? $full['customer_whatsapp'] ?? ''),
            'userName' => (string) ($full['customer_name'] ?? ''),
        ],
        'product' => [
            'name' => 'Order ' . (string) ($full['order_number'] ?? $p['payment_reference']),
            'description' => 'Oyejo Gas order payment',
        ],
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = pay_opay_sign($json, $prv);
    [$ok, $raw, $data] = pay_http(pay_opay_base() . '/api/v1/international/cashier/create', 'POST', [
        'Content-Type: application/json',
        'MerchantId: ' . $mid,
        'Authorization: Bearer ' . $sig,
    ], $json);
    $cashier = is_array($data) ? ($data['data']['cashierUrl'] ?? $data['data']['payUrl'] ?? '') : '';
    if (!$ok || $cashier === '') {
        $msg = is_array($data) ? (string) ($data['message'] ?? $data['msg'] ?? 'Opay initialize failed') : 'Opay initialize failed';
        return [false, $msg, null];
    }
    pay_mark_gateway((int) $p['id'], 'opay', (string) $p['payment_reference']);
    return [true, 'Redirecting to Opay.', (string) $cashier];
}

function pay_opay_verify(array $p) {
    $prv = trim((string) env('OPAY_PRIVATE_KEY', getenv('OPAY_PRIVATE_KEY') ?: ''));
    $mid = trim((string) env('OPAY_MERCHANT_ID', getenv('OPAY_MERCHANT_ID') ?: ''));
    if ($prv === '' || $mid === '') {
        return [false, 'Opay is not configured.'];
    }
    $payload = ['country' => 'NG', 'reference' => (string) $p['payment_reference']];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = pay_opay_sign($json, $prv);
    [$ok, $raw, $data] = pay_http(pay_opay_base() . '/api/v1/international/cashier/status', 'POST', [
        'Content-Type: application/json',
        'MerchantId: ' . $mid,
        'Authorization: Bearer ' . $sig,
    ], $json);
    $st = strtoupper((string) ($data['data']['status'] ?? $data['data']['orderStatus'] ?? ''));
    if ($ok && in_array($st, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'], true)) {
        pay_mark_gateway((int) $p['id'], 'opay', (string) $p['payment_reference']);
        return pay_verify((int) $p['id'], true, null, 'opay verify');
    }
    return [false, 'Opay has not confirmed this payment yet.'];
}

function pay_reports() {
    $pdo = db();
    return [
        'by_method' => $pdo->query(
            "SELECT `method`, `status`, COUNT(*) AS n, COALESCE(SUM(`amount_minor`), 0) AS total
             FROM `payments` GROUP BY `method`, `status` ORDER BY `method`, `status`"
        )->fetchAll(),
        'refunds' => $pdo->query(
            "SELECT `status`, COUNT(*) AS n, COALESCE(SUM(`amount_minor`), 0) AS total
             FROM `refunds` GROUP BY `status`"
        )->fetchAll(),
        'cash' => $pdo->query(
            'SELECT COALESCE(SUM(`cash_expected_minor`), 0) AS expected,
                    COALESCE(SUM(`cash_collected_minor`), 0) AS collected FROM `deliveries`'
        )->fetch(),
        'cash_recon' => $pdo->query(
            'SELECT `reconciled`, COUNT(*) AS n, COALESCE(SUM(`amount_minor`), 0) AS total
             FROM `driver_cash_collections` GROUP BY `reconciled`'
        )->fetchAll(),
        'daily' => $pdo->query(
            "SELECT DATE(`verified_at`) AS d, COUNT(*) AS n, COALESCE(SUM(`amount_minor`), 0) AS total
             FROM `payments` WHERE `status` IN ('verified','partially_refunded','refunded')
               AND `verified_at` >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY d ORDER BY d DESC"
        )->fetchAll(),
    ];
}
