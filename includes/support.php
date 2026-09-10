<?php
/**
 * Oyejo Gas - support tickets, complaints, reviews and ratings (Phase 18).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function sup_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'support', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function sup_categories() {
    return [
        'order' => 'Order', 'payment' => 'Payment', 'delivery' => 'Delivery',
        'refill' => 'Refill', 'product' => 'Product',
        'complaint' => 'Complaint', 'refund' => 'Refund request', 'other' => 'Other',
    ];
}

function sup_priorities() {
    return ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
}

function sup_statuses() {
    return ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];
}

function sup_number($pdo) {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 5; $i++) {
        $r = '';
        for ($j = 0; $j < 6; $j++) {
            $r .= $abc[random_int(0, strlen($abc) - 1)];
        }
        $num = 'TKT-' . $r;
        $stmt = $pdo->prepare('SELECT 1 FROM `support_tickets` WHERE `ticket_number` = ?');
        $stmt->execute([$num]);
        if (!$stmt->fetchColumn()) {
            return $num;
        }
    }
    return 'TKT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

/**
 * Create a ticket for a customer (or a guest via the contact form).
 * Returns [id|null, number|error].
 */
function sup_create($customer_id, $guest_email, $category, $priority, $subject, $body, $order_id = 0, $author_user_id = 0) {
    $cats = sup_categories();
    $pris = sup_priorities();
    $subject = trim((string) $subject);
    $body = trim((string) $body);
    if (!isset($cats[$category])) {
        return [null, 'Choose a valid category.'];
    }
    if (!isset($pris[$priority])) {
        return [null, 'Choose a valid priority.'];
    }
    if (mb_strlen($subject) < 5 || mb_strlen($subject) > 190) {
        return [null, 'Subject must be 5–190 characters.'];
    }
    if (mb_strlen($body) < 5 || mb_strlen($body) > 5000) {
        return [null, 'Message must be 5–5000 characters.'];
    }
    $customer_id = (int) $customer_id > 0 ? (int) $customer_id : null;
    $guest_email = trim((string) $guest_email);
    if ($customer_id === null) {
        if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            return [null, 'A valid email address is required.'];
        }
    } else {
        $guest_email = '';
    }
    $pdo = db();
    $order_id = (int) $order_id > 0 ? (int) $order_id : null;
    if ($order_id && $customer_id) {
        $stmt = $pdo->prepare('SELECT 1 FROM `orders` WHERE `id` = ? AND `customer_id` = ?');
        $stmt->execute([$order_id, $customer_id]);
        if (!$stmt->fetchColumn()) {
            return [null, 'That order is not yours.'];
        }
    } elseif ($order_id) {
        $order_id = null;
    }
    try {
        $pdo->beginTransaction();
        $num = sup_number($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO `support_tickets` (`ticket_number`, `customer_id`, `guest_email`, `order_id`,
             `category`, `priority`, `status`, `subject`)
             VALUES (?, ?, ?, ?, ?, ?, \'open\', ?)'
        );
        $stmt->execute([$num, $customer_id, $guest_email ?: null, $order_id, $category, $priority, $subject]);
        $tid = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `body`, `is_internal`) VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([$tid, (int) $author_user_id > 0 ? (int) $author_user_id : null, $body]);
        sup_audit('support.create', (int) $author_user_id ?: null, $tid, null,
            ['number' => $num, 'category' => $category]);
        $pdo->commit();
        return [$tid, $num];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('sup_create: ' . $e->getMessage());
        return [null, 'Could not create the ticket. Please try again.'];
    }
}

function sup_for_customer($cid, $limit = 50) {
    $stmt = db()->prepare(
        'SELECT t.*, o.`order_number` FROM `support_tickets` t
         LEFT JOIN `orders` o ON o.`id` = t.`order_id`
         WHERE t.`customer_id` = ? ORDER BY t.`id` DESC LIMIT ' . max(1, min(200, (int) $limit))
    );
    $stmt->execute([(int) $cid]);
    return $stmt->fetchAll();
}

function sup_get_for_customer($id, $cid) {
    $stmt = db()->prepare(
        'SELECT t.*, o.`order_number` FROM `support_tickets` t
         LEFT JOIN `orders` o ON o.`id` = t.`order_id`
         WHERE t.`id` = ? AND t.`customer_id` = ? LIMIT 1'
    );
    $stmt->execute([(int) $id, (int) $cid]);
    $t = $stmt->fetch();
    if (!$t) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT r.*, u.`name` AS author FROM `ticket_replies` r
         LEFT JOIN `users` u ON u.`id` = r.`user_id`
         WHERE r.`ticket_id` = ? AND r.`is_internal` = 0 ORDER BY r.`id`'
    );
    $stmt->execute([(int) $id]);
    $t['replies'] = $stmt->fetchAll();
    return $t;
}

function sup_reply_customer($id, $cid, $uid, $body) {
    $body = trim((string) $body);
    if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) {
        return [false, 'Reply must be 2–5000 characters.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `support_tickets` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $id, (int) $cid]);
    $t = $stmt->fetch();
    if (!$t) {
        return [false, 'Ticket not found.'];
    }
    if ($t['status'] === 'closed') {
        return [false, 'This ticket is closed. Open a new one if needed.'];
    }
    $pdo->prepare('INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `body`, `is_internal`) VALUES (?, ?, ?, 0)')
        ->execute([(int) $id, (int) $uid, $body]);
    if ($t['status'] === 'resolved') {
        $pdo->prepare("UPDATE `support_tickets` SET `status` = 'open' WHERE `id` = ?")->execute([(int) $id]);
    }
    return [true, 'Reply sent.'];
}

function sup_close_customer($id, $cid, $uid) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT `status` FROM `support_tickets` WHERE `id` = ? AND `customer_id` = ?');
    $stmt->execute([(int) $id, (int) $cid]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        return [false, 'Ticket not found.'];
    }
    if ($st === 'closed') {
        return [false, 'Already closed.'];
    }
    $pdo->prepare("UPDATE `support_tickets` SET `status` = 'closed' WHERE `id` = ?")->execute([(int) $id]);
    sup_audit('support.customer_close', $uid, (int) $id, ['status' => $st], ['status' => 'closed']);
    return [true, 'Ticket closed.'];
}

/* ---------------- staff side ---------------- */

function sup_list($status = '', $category = '', $priority = '', $q = '', $limit = 100) {
    $sql = 'SELECT t.*, o.`order_number`, u.`name` AS customer_name
            FROM `support_tickets` t
            LEFT JOIN `orders` o ON o.`id` = t.`order_id`
            LEFT JOIN `customers` c ON c.`id` = t.`customer_id`
            LEFT JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (isset(sup_statuses()[$status])) {
        $sql .= ' AND t.`status` = ?';
        $args[] = $status;
    }
    if (isset(sup_categories()[$category])) {
        $sql .= ' AND t.`category` = ?';
        $args[] = $category;
    }
    if (isset(sup_priorities()[$priority])) {
        $sql .= ' AND t.`priority` = ?';
        $args[] = $priority;
    }
    if ($q !== '') {
        $sql .= ' AND (t.`ticket_number` LIKE ? OR t.`subject` LIKE ? OR t.`guest_email` LIKE ? OR u.`name` LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        array_push($args, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY t.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function sup_get($id) {
    $stmt = db()->prepare(
        'SELECT t.*, o.`order_number`, u.`name` AS customer_name, u.`email` AS customer_email
         FROM `support_tickets` t
         LEFT JOIN `orders` o ON o.`id` = t.`order_id`
         LEFT JOIN `customers` c ON c.`id` = t.`customer_id`
         LEFT JOIN `users` u ON u.`id` = c.`user_id`
         WHERE t.`id` = ? LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    $t = $stmt->fetch();
    if (!$t) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT r.*, u.`name` AS author FROM `ticket_replies` r
         LEFT JOIN `users` u ON u.`id` = r.`user_id`
         WHERE r.`ticket_id` = ? ORDER BY r.`id`'
    );
    $stmt->execute([(int) $id]);
    $t['replies'] = $stmt->fetchAll();
    return $t;
}

function sup_notify_customer($t, $subject, $body) {
    if (empty($t['customer_id'])) {
        return;
    }
    try {
        $stmt = db()->prepare(
            'SELECT u.`email` FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?'
        );
        $stmt->execute([(int) $t['customer_id']]);
        notify_emit((int) $t['customer_id'], 'ticket_update', (string) $stmt->fetchColumn(), $subject, $body, 'email');
    } catch (Throwable $e) {
        error_log('sup_notify_customer: ' . $e->getMessage());
    }
}

function sup_reply_staff($id, $uid, $body, $internal) {
    $body = trim((string) $body);
    if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) {
        return [false, 'Reply must be 2–5000 characters.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `support_tickets` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetch();
    if (!$t) {
        return [false, 'Ticket not found.'];
    }
    if ($t['status'] === 'closed') {
        return [false, 'Reopen the ticket before replying.'];
    }
    $pdo->prepare('INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `body`, `is_internal`) VALUES (?, ?, ?, ?)')
        ->execute([(int) $id, (int) $uid, $body, $internal ? 1 : 0]);
    if (!$internal && in_array($t['status'], ['open', 'resolved'], true)) {
        $pdo->prepare("UPDATE `support_tickets` SET `status` = 'pending' WHERE `id` = ?")->execute([(int) $id]);
    }
    sup_audit('support.reply', $uid, (int) $id, null, ['internal' => $internal ? 1 : 0]);
    if (!$internal) {
        sup_notify_customer($t, 'New reply on ' . $t['ticket_number'], 'Support replied to "' . $t['subject'] . '".');
    }
    return [true, $internal ? 'Internal note saved.' : 'Reply sent to customer.'];
}

function sup_set_status($id, $to, $actor_id) {
    if (!isset(sup_statuses()[$to])) {
        return [false, 'Unknown status.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `support_tickets` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetch();
    if (!$t) {
        return [false, 'Ticket not found.'];
    }
    $pdo->prepare('UPDATE `support_tickets` SET `status` = ? WHERE `id` = ?')->execute([$to, (int) $id]);
    sup_audit('support.status', $actor_id, (int) $id, ['status' => $t['status']], ['status' => $to]);
    if (in_array($to, ['resolved', 'closed'], true)) {
        sup_notify_customer($t, 'Ticket ' . $t['ticket_number'] . ' ' . $to, '"' . $t['subject'] . '" is now ' . $to . '.');
    }
    return [true, $t['ticket_number'] . ' is now ' . $to . '.'];
}

function sup_set_priority($id, $to, $actor_id) {
    if (!isset(sup_priorities()[$to])) {
        return [false, 'Unknown priority.'];
    }
    $stmt = db()->prepare('SELECT `priority`, `ticket_number` FROM `support_tickets` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $t = $stmt->fetch();
    if (!$t) {
        return [false, 'Ticket not found.'];
    }
    db()->prepare('UPDATE `support_tickets` SET `priority` = ? WHERE `id` = ?')->execute([$to, (int) $id]);
    sup_audit('support.priority', $actor_id, (int) $id, ['priority' => $t['priority']], ['priority' => $to]);
    return [true, $t['ticket_number'] . ' priority set to ' . $to . '.'];
}

/* ---------------- reviews & ratings (ST-11…ST-13) ---------------- */

function rev_submit($customer_id, $kind, $ref_id, $rating, $title, $body) {
    $rating = (int) $rating;
    $title = mb_substr(trim((string) $title), 0, 190);
    $body = mb_substr(trim((string) $body), 0, 5000);
    if ($rating < 1 || $rating > 5) {
        return [false, 'Rating must be 1–5 stars.'];
    }
    if ($body !== '' && mb_strlen($body) < 3) {
        return [false, 'Review text is too short.'];
    }
    $pdo = db();
    $product_id = $order_id = $delivery_id = null;
    if ($kind === 'product') {
        // Verified purchase: a delivered/completed order containing the product.
        $stmt = $pdo->prepare(
            "SELECT o.`id` FROM `orders` o JOIN `order_items` oi ON oi.`order_id` = o.`id`
             WHERE o.`customer_id` = ? AND oi.`product_id` = ? AND o.`status` IN ('delivered','completed') LIMIT 1"
        );
        $stmt->execute([(int) $customer_id, (int) $ref_id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'You can only review products from a delivered order.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `products` WHERE `id` = ?');
        $stmt->execute([(int) $ref_id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Product not found.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `reviews` WHERE `customer_id` = ? AND `product_id` = ? LIMIT 1');
        $stmt->execute([(int) $customer_id, (int) $ref_id]);
        if ($stmt->fetchColumn()) {
            return [false, 'You already reviewed that product.'];
        }
        $product_id = (int) $ref_id;
    } elseif ($kind === 'order') {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM `orders` WHERE `id` = ? AND `customer_id` = ? AND `status` IN ('delivered','completed')"
        );
        $stmt->execute([(int) $ref_id, (int) $customer_id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'You can only review your own delivered orders.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `reviews` WHERE `customer_id` = ? AND `order_id` = ? LIMIT 1');
        $stmt->execute([(int) $customer_id, (int) $ref_id]);
        if ($stmt->fetchColumn()) {
            return [false, 'You already reviewed that order.'];
        }
        $order_id = (int) $ref_id;
    } elseif ($kind === 'delivery') {
        $stmt = $pdo->prepare(
            "SELECT d.`id` FROM `deliveries` d
             LEFT JOIN `orders` o ON o.`id` = d.`order_id`
             LEFT JOIN `refills` r ON r.`id` = d.`refill_id`
             LEFT JOIN `pickups` p ON p.`id` = d.`pickup_id`
             WHERE d.`id` = ? AND d.`status` = 'delivered'
               AND COALESCE(o.`customer_id`, r.`customer_id`, p.`customer_id`) = ? LIMIT 1"
        );
        $stmt->execute([(int) $ref_id, (int) $customer_id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'You can only review your own completed deliveries.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `reviews` WHERE `customer_id` = ? AND `delivery_id` = ? LIMIT 1');
        $stmt->execute([(int) $customer_id, (int) $ref_id]);
        if ($stmt->fetchColumn()) {
            return [false, 'You already reviewed that delivery.'];
        }
        $delivery_id = (int) $ref_id;
    } else {
        return [false, 'Unknown review type.'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO `reviews` (`customer_id`, `product_id`, `order_id`, `delivery_id`, `rating`, `title`, `body`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\')'
    );
    $stmt->execute([(int) $customer_id, $product_id, $order_id, $delivery_id, $rating, $title ?: null, $body ?: null]);
    sup_audit('support.review_submit', null, (int) $pdo->lastInsertId(), null, ['kind' => $kind, 'rating' => $rating]);
    return [true, 'Review submitted — it appears after moderation.'];
}

function rev_for_customer($cid) {
    $stmt = db()->prepare(
        'SELECT r.*, p.`name` AS product_name, o.`order_number`, d.`delivery_number`
         FROM `reviews` r
         LEFT JOIN `products` p ON p.`id` = r.`product_id`
         LEFT JOIN `orders` o ON o.`id` = r.`order_id`
         LEFT JOIN `deliveries` d ON d.`id` = r.`delivery_id`
         WHERE r.`customer_id` = ? ORDER BY r.`id` DESC LIMIT 100'
    );
    $stmt->execute([(int) $cid]);
    return $stmt->fetchAll();
}

function rev_list($status = '', $limit = 100) {
    $sql = 'SELECT r.*, p.`name` AS product_name, o.`order_number`, d.`delivery_number`, u.`name` AS customer_name
            FROM `reviews` r
            LEFT JOIN `products` p ON p.`id` = r.`product_id`
            LEFT JOIN `orders` o ON o.`id` = r.`order_id`
            LEFT JOIN `deliveries` d ON d.`id` = r.`delivery_id`
            JOIN `customers` c ON c.`id` = r.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $sql .= ' AND r.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY r.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function rev_moderate($id, $to, $actor_id) {
    if (!in_array($to, ['approved', 'rejected'], true)) {
        return [false, 'Unknown decision.'];
    }
    $stmt = db()->prepare('SELECT `status` FROM `reviews` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        return [false, 'Review not found.'];
    }
    if ($st !== 'pending') {
        return [false, 'Only pending reviews can be moderated.'];
    }
    db()->prepare('UPDATE `reviews` SET `status` = ? WHERE `id` = ?')->execute([$to, (int) $id]);
    sup_audit('support.review_moderate', $actor_id, (int) $id, ['status' => 'pending'], ['status' => $to]);
    return [true, 'Review ' . $to . '.'];
}

function rev_product_summary($product_id) {
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS n, COALESCE(AVG(`rating`), 0) AS avg FROM `reviews`
         WHERE `product_id` = ? AND `status` = 'approved'"
    );
    $stmt->execute([(int) $product_id]);
    return $stmt->fetch();
}

function rev_product_list($product_id, $limit = 20) {
    $stmt = db()->prepare(
        "SELECT r.`rating`, r.`title`, r.`body`, r.`created_at`, u.`name` AS customer_name
         FROM `reviews` r JOIN `customers` c ON c.`id` = r.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id`
         WHERE r.`product_id` = ? AND r.`status` = 'approved'
         ORDER BY r.`id` DESC LIMIT " . max(1, min(50, (int) $limit))
    );
    $stmt->execute([(int) $product_id]);
    return $stmt->fetchAll();
}

/** Eligible review targets for a customer. */
function rev_eligible_products($cid) {
    $stmt = db()->prepare(
        "SELECT DISTINCT p.`id`, p.`name` FROM `order_items` oi
         JOIN `orders` o ON o.`id` = oi.`order_id`
         JOIN `products` p ON p.`id` = oi.`product_id`
         WHERE o.`customer_id` = ? AND o.`status` IN ('delivered','completed')
           AND NOT EXISTS (SELECT 1 FROM `reviews` r WHERE r.`customer_id` = ? AND r.`product_id` = p.`id`)
         ORDER BY p.`name` LIMIT 100"
    );
    $stmt->execute([(int) $cid, (int) $cid]);
    return $stmt->fetchAll();
}

function rev_eligible_deliveries($cid) {
    $stmt = db()->prepare(
        "SELECT d.`id`, d.`delivery_number` FROM `deliveries` d
         LEFT JOIN `orders` o ON o.`id` = d.`order_id`
         LEFT JOIN `refills` r ON r.`id` = d.`refill_id`
         LEFT JOIN `pickups` p ON p.`id` = d.`pickup_id`
         WHERE d.`status` = 'delivered' AND COALESCE(o.`customer_id`, r.`customer_id`, p.`customer_id`) = ?
           AND NOT EXISTS (SELECT 1 FROM `reviews` rv WHERE rv.`customer_id` = ? AND rv.`delivery_id` = d.`id`)
         ORDER BY d.`id` DESC LIMIT 100"
    );
    $stmt->execute([(int) $cid, (int) $cid]);
    return $stmt->fetchAll();
}

/* ---------------- reports (ST-14) ---------------- */

function sup_reports() {
    $pdo = db();
    return [
        'by_status' => $pdo->query('SELECT `status`, COUNT(*) AS n FROM `support_tickets` GROUP BY `status`')->fetchAll(),
        'by_category' => $pdo->query('SELECT `category`, COUNT(*) AS n FROM `support_tickets` GROUP BY `category`')->fetchAll(),
        'by_priority' => $pdo->query('SELECT `priority`, COUNT(*) AS n FROM `support_tickets` GROUP BY `priority`')->fetchAll(),
        'reviews' => $pdo->query('SELECT `status`, COUNT(*) AS n, COALESCE(AVG(`rating`), 0) AS avg FROM `reviews` GROUP BY `status`')->fetchAll(),
    ];
}
