<?php
/**
 * Oyejo Gas - notification emitter + inbox reader (Phase 10).
 * Rows land in `notifications` as queued; channel delivery workers arrive in
 * Phase 22. Emitting never throws: notifications must not break orders.
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
        'SELECT * FROM `notifications` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT ' . (int) $limit
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}
