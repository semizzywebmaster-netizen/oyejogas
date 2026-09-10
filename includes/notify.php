<?php
/**
 * Oyejo Gas - notification emitter, dispatcher and queue worker (Phase 22).
 * Rows land in `notifications` as queued; notify_process_queue() delivers
 * them per channel with retries, rate limits and opt-in enforcement.
 * Emitting never throws: notifications must not break orders.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function notify_emit($customer_id, $event, $recipient, $subject, $body, $channel = 'email') {
    try {
        $allowed = ['email', 'sms', 'whatsapp', 'push'];
        if (!in_array($channel, $allowed, true)) {
            $channel = 'email';
        }
        db()->prepare(
            'INSERT INTO `notifications` (`customer_id`, `channel`, `event`, `recipient`, `subject`, `body`, `status`)'
            . " VALUES (?, ?, ?, ?, ?, ?, 'queued')"
        )->execute([
            (int) $customer_id, $channel, substr((string) $event, 0, 100),
            substr((string) $recipient, 0, 190),
            $subject !== null && $subject !== '' ? substr((string) $subject, 0, 190) : null,
            $body,
        ]);
        return true;
    } catch (Throwable $t) {
        return false;
    }
}

function notify_for_customer($customer_id, $limit = 50) {
    $s = db()->prepare(
        'SELECT * FROM `notifications` WHERE `customer_id` = ? AND `recipient` NOT LIKE \'pickup:%\' ORDER BY `id` DESC LIMIT ' . (int) $limit
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}

/* ---------------- event catalogue (NT-01…NT-15) ---------------- */

function notify_events() {
    return [
        'registration' => ['Registration', ['email']],
        'verification' => ['Verification', ['email']],
        'password_reset' => ['Password reset', ['email']],
        'order_confirmation' => ['Order confirmation', ['email', 'whatsapp']],
        'payment_confirmation' => ['Payment confirmation', ['email', 'whatsapp']],
        'refill_requested' => ['Refill requests', ['email', 'sms']],
        'pickup_reminder' => ['Pickup reminders', ['sms', 'whatsapp']],
        'driver_assigned' => ['Driver assignment', ['email', 'sms']],
        'out_for_delivery' => ['Out for delivery', ['email', 'sms', 'whatsapp']],
        'delivery_completion' => ['Delivery completion', ['email', 'whatsapp']],
        'wallet_transaction' => ['Wallet transactions', ['email']],
        'referral_reward' => ['Referral rewards', ['email']],
        'spin_reward' => ['Spin rewards', ['email']],
        'ticket_update' => ['Support-ticket updates', ['email']],
        'promo' => ['Promotional campaigns', ['email', 'whatsapp']],
    ];
}

function notify_channels() {
    return ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'push' => 'Push (in-app)'];
}

function notify_channel_toggles() {
    return [
        'email' => 'email_notifications',
        'sms' => 'sms_notifications',
        'whatsapp' => 'whatsapp_notifications',
        'push' => 'push_notifications',
    ];
}

/** Per-event channel config (settings override code defaults). */
function notify_config($event) {
    $events = notify_events();
    $default = isset($events[$event]) ? $events[$event][1] : ['email'];
    try {
        $stmt = db()->prepare('SELECT `value` FROM `settings` WHERE `key` = ?');
        $stmt->execute(['notify_event_' . $event]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || trim((string) $raw) === '') {
            return $default;
        }
        $out = [];
        foreach (explode(',', (string) $raw) as $c) {
            $c = trim($c);
            if (isset(notify_channels()[$c]) && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
        return $out ?: $default;
    } catch (Throwable $t) {
        return $default;
    }
}

function notify_config_save($event, array $channels, $actor_id) {
    if (!isset(notify_events()[$event])) {
        return [false, 'Unknown event.'];
    }
    $clean = [];
    foreach ($channels as $c) {
        $c = trim((string) $c);
        if (isset(notify_channels()[$c]) && !in_array($c, $clean, true)) {
            $clean[] = $c;
        }
    }
    if (!$clean) {
        return [false, 'Pick at least one channel.'];
    }
    db()->prepare('INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, \'notifications\')
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
        ->execute(['notify_event_' . $event, implode(',', $clean)]);
    notify_audit('notify.config', $actor_id, null, ['event' => $event], ['channels' => $clean]);
    return [true, 'Channel config saved.'];
}

function notify_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'notify', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/* ---------------- templates (WA-02) ---------------- */

function notify_templates_all() {
    return db()->query('SELECT * FROM `notification_templates` ORDER BY `event`, `channel`')->fetchAll();
}

function notify_template_save($id, $data, $actor_id) {
    $slug = strtolower(trim((string) ($data['slug'] ?? '')));
    $channel = (string) ($data['channel'] ?? 'email');
    $event = trim((string) ($data['event'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
        return [false, 'Slug must be 1–120 chars: lowercase, numbers, dash.'];
    }
    if (!isset(notify_channels()[$channel])) {
        return [false, 'Invalid channel.'];
    }
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $event)) {
        return [false, 'Invalid event name.'];
    }
    if (mb_strlen($subject) > 190) {
        return [false, 'Subject is too long (max 190).'];
    }
    if (mb_strlen($body) < 5 || mb_strlen($body) > 10000) {
        return [false, 'Body must be 5–10000 characters.'];
    }
    $active = isset($data['is_active']) ? 1 : 0;
    $id = (int) $id;
    $stmt = db()->prepare('SELECT `id` FROM `notification_templates` WHERE `slug` = ? AND `id` <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That slug is already used.'];
    }
    if ($id > 0) {
        $stmt = db()->prepare('SELECT `slug` FROM `notification_templates` WHERE `id` = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() === false) {
            return [false, 'Template not found.'];
        }
        db()->prepare('UPDATE `notification_templates` SET `slug` = ?, `channel` = ?, `event` = ?, `subject` = ?, `body` = ?, `is_active` = ? WHERE `id` = ?')
            ->execute([$slug, $channel, $event, $subject ?: null, $body, $active, $id]);
        notify_audit('notify.template_save', $actor_id, $id, null, ['slug' => $slug]);
        return [true, 'Template saved.'];
    }
    db()->prepare('INSERT INTO `notification_templates` (`slug`, `channel`, `event`, `subject`, `body`, `is_active`) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$slug, $channel, $event, $subject ?: null, $body, $active]);
    notify_audit('notify.template_create', $actor_id, (int) db()->lastInsertId(), null, ['slug' => $slug]);
    return [true, 'Template created.'];
}

function notify_template_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT `slug` FROM `notification_templates` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetchColumn();
    if ($t === false) {
        return [false, 'Template not found.'];
    }
    db()->prepare('DELETE FROM `notification_templates` WHERE `id` = ?')->execute([(int) $id]);
    notify_audit('notify.template_delete', $actor_id, (int) $id, ['slug' => $t], null);
    return [true, 'Template deleted.'];
}

/** Render active template for event+channel; null when none. */
function notify_render($event, $channel, array $vars) {
    $stmt = db()->prepare(
        'SELECT `subject`, `body` FROM `notification_templates`
         WHERE `event` = ? AND `channel` = ? AND `is_active` = 1 ORDER BY `id` LIMIT 1'
    );
    $stmt->execute([$event, $channel]);
    $t = $stmt->fetch();
    if (!$t) {
        return null;
    }
    $vars['site'] = APP_NAME;
    $sub = (string) ($t['subject'] ?? '');
    $body = (string) $t['body'];
    foreach ($vars as $k => $v) {
        $sub = str_replace('{{' . $k . '}}', (string) $v, $sub);
        $body = str_replace('{{' . $k . '}}', (string) $v, $body);
    }
    return [$sub !== '' ? $sub : null, $body];
}

/* ---------------- recipients & WhatsApp opt-in (WA-03) ---------------- */

function notify_recipient($customer_id) {
    $stmt = db()->prepare(
        'SELECT u.`name`, u.`email`, u.`phone` FROM `customers` c
         JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?'
    );
    $stmt->execute([(int) $customer_id]);
    return $stmt->fetch() ?: ['name' => '', 'email' => '', 'phone' => ''];
}

/** Missing row = subscribed; explicit 0 = opted out. */
function notify_wa_subscribed($customer_id) {
    $stmt = db()->prepare('SELECT `subscribed` FROM `whatsapp_subscriptions` WHERE `customer_id` = ?');
    $stmt->execute([(int) $customer_id]);
    $v = $stmt->fetchColumn();
    return $v === false ? true : ((int) $v === 1);
}

function notify_wa_set($customer_id, $subscribed) {
    db()->prepare('INSERT INTO `whatsapp_subscriptions` (`customer_id`, `subscribed`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `subscribed` = VALUES(`subscribed`)')
        ->execute([(int) $customer_id, $subscribed ? 1 : 0]);
    return [true, $subscribed ? 'WhatsApp notifications on.' : 'WhatsApp notifications off.'];
}

/* ---------------- dispatcher ---------------- */

/**
 * Queue a templated multi-channel notification. Returns rows queued.
 * Never throws.
 */
function notify_send($event, $customer_id, array $vars = [], $subject = '', $body = '', $channels = null) {
    try {
        if (!isset(notify_events()[$event])) {
            return 0;
        }
        $channels = $channels === null ? notify_config($event) : (array) $channels;
        $toggles = notify_channel_toggles();
        $to = notify_recipient($customer_id);
        $vars += ['name' => $to['name'], 'email' => $to['email'], 'phone' => $to['phone']];
        $n = 0;
        foreach ($channels as $ch) {
            if (!isset(notify_channels()[$ch])) {
                continue;
            }
            if ($toggles[$ch] !== null && !oyejo_feature($toggles[$ch])) {
                continue;
            }
            $recipient = in_array($ch, ['sms', 'whatsapp'], true) ? $to['phone'] : $to['email'];
            if ($ch === 'push') {
                $recipient = $to['email'] !== '' ? $to['email'] : ('customer:' . (int) $customer_id);
            }
            if ($recipient === '') {
                continue;
            }
            if ($ch === 'whatsapp' && !notify_wa_subscribed($customer_id)) {
                continue;
            }
            $rendered = notify_render($event, $ch, $vars);
            if ($rendered) {
                [$s, $b] = $rendered;
            } else {
                $s = $subject !== '' ? $subject : null;
                $b = $body;
            }
            if (notify_emit((int) $customer_id, $event, $recipient, $s, $b, $ch)) {
                $n++;
            }
        }
        return $n;
    } catch (Throwable $t) {
        return 0;
    }
}

function notify_limits() {
    $out = ['notify_rate_per_minute' => 30, 'notify_max_attempts' => 5];
    try {
        foreach (db()->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('notify_rate_per_minute','notify_max_attempts')")->fetchAll() as $r) {
            if (is_numeric($r['value'])) {
                $out[$r['key']] = (int) $r['value'];
            }
        }
    } catch (Throwable $t) {
        // Defaults above.
    }
    $out['notify_rate_per_minute'] = max(1, min(1000, $out['notify_rate_per_minute']));
    $out['notify_max_attempts'] = max(1, min(20, $out['notify_max_attempts']));
    return $out;
}

/** Deliver one queued row. Returns 'sent', 'failed' or 'skipped'. */
function notify_deliver_row(array $row) {
    $pdo = db();
    $id = (int) $row['id'];
    $ch = $row['channel'];
    $toggle = notify_channel_toggles()[$ch] ?? null;
    if ($toggle !== null && !oyejo_feature($toggle)) {
        return 'skipped';
    }
    if ($ch === 'push') {
        $pdo->prepare("UPDATE `notifications` SET `status` = 'delivered', `provider` = 'inbox', `sent_at` = NOW() WHERE `id` = ?")
            ->execute([$id]);
        return 'sent';
    }
    if ($ch === 'whatsapp' && (int) $row['customer_id'] > 0 && !notify_wa_subscribed((int) $row['customer_id'])) {
        $pdo->prepare("UPDATE `notifications` SET `status` = 'failed', `error` = 'Customer opted out', `attempts` = 999 WHERE `id` = ?")
            ->execute([$id]);
        return 'failed';
    }
    if ($ch === 'email') {
        send_mail($row['recipient'], (string) ($row['subject'] ?? ''), (string) $row['body']);
        [$ok, $mid] = [true, env_bool('MAIL_REAL', false) ? 'mail' : 'log'];
        $provider = $mid === 'log' ? 'log' : (env('MAIL_FROM') ? 'smtp' : 'mail');
    } elseif ($ch === 'sms') {
        [$ok, $mid] = sms_send($row['recipient'], (string) $row['body']);
        $provider = $mid === 'log' ? 'log' : (string) env('SMS_PROVIDER', 'sms');
    } elseif ($ch === 'whatsapp') {
        [$ok, $mid] = whatsapp_send($row['recipient'], (string) $row['body']);
        $provider = $mid === 'log' ? 'log' : (string) env('WHATSAPP_PROVIDER', 'whatsapp');
    } else {
        return 'skipped';
    }
    if ($ok) {
        $pdo->prepare("UPDATE `notifications` SET `status` = 'sent', `provider` = ?, `provider_msg_id` = ?, `sent_at` = NOW(), `error` = NULL WHERE `id` = ?")
            ->execute([$provider, $mid !== '' ? $mid : null, $id]);
        return 'sent';
    }
    $attempts = (int) $row['attempts'] + 1;
    $max = notify_limits()['notify_max_attempts'];
    if ($attempts >= $max) {
        $pdo->prepare("UPDATE `notifications` SET `status` = 'failed', `attempts` = ?, `error` = ? WHERE `id` = ?")
            ->execute([$attempts, substr($mid, 0, 500), $id]);
    } else {
        $pdo->prepare("UPDATE `notifications` SET `attempts` = ?, `error` = ?, `next_retry_at` = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE `id` = ?")
            ->execute([$attempts, substr($mid, 0, 500), 5 * $attempts, $id]);
    }
    return 'failed';
}

/**
 * Queue worker (WA-04…WA-07). Returns [processed, sent, failed, note].
 * Never throws.
 */
function notify_process_queue($limit = 100) {
    try {
        $pdo = db();
        $lim = notify_limits();
        $recent = (int) $pdo->query(
            "SELECT COUNT(*) FROM `notifications` WHERE `status` IN ('sent','delivered') AND `sent_at` >= NOW() - INTERVAL 1 MINUTE"
        )->fetchColumn();
        if ($recent >= $lim['notify_rate_per_minute']) {
            return [0, 0, 0, 'rate limited (' . $recent . '/min)'];
        }
        $rows = $pdo->query(
            "SELECT * FROM `notifications` WHERE `status` = 'queued'
             AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW())
             ORDER BY `id` LIMIT " . max(1, min(500, (int) $limit))
        )->fetchAll();
        $sent = $failed = 0;
        foreach ($rows as $r) {
            $res = notify_deliver_row($r);
            if ($res === 'sent') {
                $sent++;
            } elseif ($res === 'failed') {
                $failed++;
            }
            $recent = (int) $pdo->query(
                "SELECT COUNT(*) FROM `notifications` WHERE `status` IN ('sent','delivered') AND `sent_at` >= NOW() - INTERVAL 1 MINUTE"
            )->fetchColumn();
            if ($recent >= $lim['notify_rate_per_minute']) {
                break;
            }
        }
        return [count($rows), $sent, $failed, ''];
    } catch (Throwable $t) {
        report_error('notifications', 'error', $t);
        return [0, 0, 0, 'worker error'];
    }
}

function notify_retry($id, $actor_id) {
    $stmt = db()->prepare('SELECT `status` FROM `notifications` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        return [false, 'Message not found.'];
    }
    if (!in_array($st, ['failed', 'queued'], true)) {
        return [false, 'Only failed or queued messages can be retried.'];
    }
    db()->prepare("UPDATE `notifications` SET `status` = 'queued', `attempts` = 0, `error` = NULL, `next_retry_at` = NULL WHERE `id` = ?")
        ->execute([(int) $id]);
    notify_audit('notify.retry', $actor_id, (int) $id, ['status' => $st], ['status' => 'queued']);
    return [true, 'Message re-queued.'];
}

/* ---------------- bulk helpers ---------------- */

/** Promotional blast (NT-15): queued, capped, rate-limited by the worker. */
function notify_promo($subject, $body, array $channels, $actor_id, $cap = 200) {
    $subject = trim((string) $subject);
    $body = trim((string) $body);
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 190) {
        return [false, 'Subject must be 3–190 characters.'];
    }
    if (mb_strlen($body) < 5 || mb_strlen($body) > 5000) {
        return [false, 'Message must be 5–5000 characters.'];
    }
    $channels = array_values(array_intersect($channels, ['email', 'whatsapp', 'sms']));
    if (!$channels) {
        return [false, 'Pick at least one channel.'];
    }
    $n = 0;
    if (in_array('email', $channels, true)) {
        $rows = db()->query("SELECT `email` FROM `newsletter_subscribers` WHERE `status` = 'subscribed' LIMIT " . max(1, min(500, (int) $cap)))->fetchAll();
        foreach ($rows as $r) {
            $rendered = notify_render('promo', 'email', []);
            if ($rendered) {
                [$s, $b] = [$rendered[0] ?? $subject, $rendered[1]];
                $b = str_replace(['{{subject}}', '{{message}}'], [$subject, $body], $b);
                $s = $s ? str_replace(['{{subject}}', '{{message}}'], [$subject, $body], $s) : $subject;
            } else {
                [$s, $b] = [$subject, $body];
            }
            db()->prepare(
                'INSERT INTO `notifications` (`customer_id`, `channel`, `event`, `recipient`, `subject`, `body`, `status`)
                 VALUES (NULL, \'email\', \'promo\', ?, ?, ?, \'queued\')'
            )->execute([$r['email'], $s, $b]);
            $n++;
        }
    }
    foreach (['whatsapp', 'sms'] as $ch) {
        if (!in_array($ch, $channels, true)) {
            continue;
        }
        $rows = db()->query(
            'SELECT u.`phone` FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id`
             WHERE u.`phone` IS NOT NULL AND u.`phone` <> \'\'
             AND NOT EXISTS (SELECT 1 FROM `whatsapp_subscriptions` w WHERE w.`customer_id` = c.`id` AND w.`subscribed` = 0)
             LIMIT ' . max(1, min(500, (int) $cap))
        )->fetchAll();
        foreach ($rows as $r) {
            $rendered = notify_render('promo', $ch, []);
            if ($rendered) {
                $b = str_replace(['{{subject}}', '{{message}}'], [$subject, $body], $rendered[1]);
            } else {
                $b = $body;
            }
            db()->prepare(
                'INSERT INTO `notifications` (`customer_id`, `channel`, `event`, `recipient`, `subject`, `body`, `status`)
                 VALUES (NULL, ?, \'promo\', ?, NULL, ?, \'queued\')'
            )->execute([$ch, $r['phone'], $b]);
            $n++;
        }
    }
    notify_audit('notify.promo', $actor_id, null, null, ['queued' => $n, 'channels' => $channels]);
    return [true, $n . ' promotional message(s) queued.'];
}

/** Pickup reminders for pickups scheduled tomorrow (NT-07). */
function notify_pickup_reminders() {
    $rows = db()->query(
        "SELECT p.`id`, p.`customer_id`, p.`pickup_number` FROM `pickups` p
         WHERE p.`status` = 'scheduled' AND p.`scheduled_date` = CURDATE() + INTERVAL 1 DAY
         AND NOT EXISTS (SELECT 1 FROM `notifications` n WHERE n.`event` = 'pickup_reminder'
           AND n.`recipient` = CONCAT('pickup:', p.`id`) AND n.`created_at` >= CURDATE())"
    )->fetchAll();
    $n = 0;
    foreach ($rows as $r) {
        $to = notify_recipient((int) $r['customer_id']);
        $vars = ['pickup_number' => $r['pickup_number'], 'scheduled_date' => date('Y-m-d', strtotime('+1 day'))];
        $before = $n;
        $n += notify_send('pickup_reminder', (int) $r['customer_id'], $vars,
            'Pickup ' . $r['pickup_number'] . ' is tomorrow',
            'Reminder: your cylinder pickup ' . $r['pickup_number'] . ' is scheduled for tomorrow.');
        if ($n > $before) {
            db()->prepare(
                'INSERT INTO `notifications` (`customer_id`, `channel`, `event`, `recipient`, `subject`, `body`, `status`)
                 VALUES (?, \'push\', \'pickup_reminder\', ?, \'sentinel\', \'sentinel\', \'delivered\')'
            )->execute([(int) $r['customer_id'], 'pickup:' . $r['id']]);
        }
    }
    return $n;
}

/* ---------------- desk queries ---------------- */

function notify_queue($status = '', $channel = '', $event = '', $limit = 100) {
    $sql = 'SELECT n.*, u.`name` AS customer_name FROM `notifications` n
            LEFT JOIN `customers` c ON c.`id` = n.`customer_id`
            LEFT JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['queued', 'sent', 'delivered', 'failed'], true)) {
        $sql .= ' AND n.`status` = ?';
        $args[] = $status;
    }
    if (isset(notify_channels()[$channel])) {
        $sql .= ' AND n.`channel` = ?';
        $args[] = $channel;
    }
    if ($event !== '') {
        $sql .= ' AND n.`event` = ?';
        $args[] = substr($event, 0, 100);
    }
    $sql .= ' ORDER BY n.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function notify_report_counts() {
    $pdo = db();
    return [
        'by_status' => $pdo->query('SELECT `status`, COUNT(*) AS n FROM `notifications` GROUP BY `status`')->fetchAll(),
        'by_channel' => $pdo->query('SELECT `channel`, COUNT(*) AS n FROM `notifications` GROUP BY `channel`')->fetchAll(),
        'by_event' => $pdo->query('SELECT `event`, COUNT(*) AS n FROM `notifications` GROUP BY `event` ORDER BY n DESC LIMIT 20')->fetchAll(),
    ];
}

function notify_wa_list($subscribed = null) {
    $sql = 'SELECT c.`id`, u.`name`, u.`phone`, w.`subscribed`, w.`updated_at`
            FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id`
            LEFT JOIN `whatsapp_subscriptions` w ON w.`customer_id` = c.`id`';
    if ($subscribed === true) {
        $sql .= ' WHERE (w.`subscribed` = 1 OR w.`customer_id` IS NULL)';
    } elseif ($subscribed === false) {
        $sql .= ' WHERE w.`subscribed` = 0';
    }
    $sql .= ' ORDER BY c.`id` DESC LIMIT 200';
    return db()->query($sql)->fetchAll();
}
