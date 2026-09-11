<?php
/**
 * Oyejo Gas - growth extras: identity columns, banner captions,
 * abandoned carts and customer-suggested locations.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function growth_has_column($table, $column) {
    static $cache = [];
    $k = $table . '.' . $column;
    if (isset($cache[$k])) {
        return $cache[$k];
    }
    try {
        $s = db()->prepare(
            'SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?'
        );
        $s->execute([$table, $column]);
        $cache[$k] = (int) $s->fetchColumn() > 0;
    } catch (Throwable $t) {
        $cache[$k] = false;
    }
    return $cache[$k];
}

function growth_ensure_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $s = db()->prepare('SELECT 1 FROM `migrations` WHERE `migration` = ?');
        $s->execute(['20260911_growth_extras']);
        if ($s->fetchColumn()) {
            return;
        }
    } catch (Throwable $t) {
        return;
    }
    try {
        if (!growth_has_column('users', 'username')) {
            db()->exec('ALTER TABLE `users` ADD COLUMN `username` VARCHAR(40) NULL AFTER `email`');
            db()->exec('ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`)');
        }
        if (!growth_has_column('users', 'whatsapp')) {
            db()->exec('ALTER TABLE `users` ADD COLUMN `whatsapp` VARCHAR(30) NULL AFTER `phone`');
            db()->exec('ALTER TABLE `users` ADD UNIQUE KEY `uq_users_whatsapp` (`whatsapp`)');
        }
        if (!growth_has_column('users', 'whatsapp_verified_at')) {
            db()->exec('ALTER TABLE `users` ADD COLUMN `whatsapp_verified_at` DATETIME NULL AFTER `phone_verified_at`');
        }
        if (!growth_has_column('banners', 'body')) {
            db()->exec('ALTER TABLE `banners` ADD COLUMN `body` TEXT NULL AFTER `title`');
        }
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `abandoned_carts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INT UNSIGNED NOT NULL,
                `items` TEXT NOT NULL,
                `notified_at` DATETIME NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_abandoned_customer` (`customer_id`),
                KEY `idx_abandoned_notify` (`notified_at`, `updated_at`),
                CONSTRAINT `fk_abandoned_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `location_suggestions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `description` VARCHAR(255) NULL,
                `status` ENUM(\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'pending\',
                `zone_id` INT UNSIGNED NULL,
                `reviewed_by` INT UNSIGNED NULL,
                `reviewed_at` DATETIME NULL,
                `review_note` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_locsug_status` (`status`, `created_at`),
                CONSTRAINT `fk_locsug_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_locsug_zone` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_locsug_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->prepare(
            'INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`)'
        )->execute(['abandoned_cart_start_at', date('Y-m-d H:i:s'), 'cart']);
        db()->prepare(
            'INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`)'
        )->execute(['abandoned_cart_idle_hours', '2', 'cart']);
        db()->prepare(
            "INSERT IGNORE INTO `notification_templates` (`slug`, `channel`, `event`, `subject`, `body`, `is_active`)
             VALUES ('abandoned-cart-whatsapp', 'whatsapp', 'abandoned_cart', NULL,
             'Hello {{name}}, you left items in your {{site}} cart. Complete checkout here: {{link}}', 1)"
        )->execute();
        db()->prepare(
            "INSERT IGNORE INTO `notification_templates` (`slug`, `channel`, `event`, `subject`, `body`, `is_active`)
             VALUES ('abandoned-cart-email', 'email', 'abandoned_cart', 'You left items in your cart',
             'Hello {{name}}, you still have items in your {{site}} cart. Finish checkout: {{link}}', 1)"
        )->execute();
        db()->prepare('INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES (?, 2)')
            ->execute(['20260911_growth_extras']);
    } catch (Throwable $t) {
        error_log('[oyejo] growth_ensure_schema: ' . $t->getMessage());
    }
}

function growth_norm_phone($raw) {
    $s = preg_replace('/\s+/', '', trim((string) $raw));
    return $s;
}

function growth_valid_phone($raw) {
    $s = growth_norm_phone($raw);
    return $s !== '' && preg_match('/^[0-9+\-]{7,20}$/', $s);
}

function growth_valid_username($raw) {
    return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,29}$/', (string) $raw);
}

/* ---------------- abandoned carts ---------------- */

function cart_persist() {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        return;
    }
    try {
        require_once BASE_PATH . '/includes/cart.php';
        $cid = cart_customer_id((int) current_user()['id']);
        if ($cid < 1) {
            return;
        }
        $items = cart_get();
        if (!$items) {
            db()->prepare('DELETE FROM `abandoned_carts` WHERE `customer_id` = ?')->execute([$cid]);
            return;
        }
        $json = json_encode($items);
        db()->prepare(
            'INSERT INTO `abandoned_carts` (`customer_id`, `items`, `notified_at`) VALUES (?, ?, NULL)
             ON DUPLICATE KEY UPDATE `items` = VALUES(`items`), `notified_at` = NULL, `updated_at` = NOW()'
        )->execute([$cid, $json]);
    } catch (Throwable $t) {
        // Table may not exist yet.
    }
}

function cart_restore_abandoned() {
    if (!is_logged_in()) {
        return;
    }
    try {
        require_once BASE_PATH . '/includes/cart.php';
        if (cart_get()) {
            return;
        }
        $cid = cart_customer_id((int) current_user()['id']);
        if ($cid < 1) {
            return;
        }
        $s = db()->prepare('SELECT `items` FROM `abandoned_carts` WHERE `customer_id` = ? LIMIT 1');
        $s->execute([$cid]);
        $raw = $s->fetchColumn();
        $items = $raw ? json_decode((string) $raw, true) : null;
        if (is_array($items) && $items) {
            $_SESSION[OYEJO_CART_KEY] = $items;
        }
    } catch (Throwable $t) {
        // Ignore.
    }
}

function abandoned_queue_reminders($cap = 100) {
    $start = trim((string) setting('abandoned_cart_start_at', ''));
    $hours = (int) setting('abandoned_cart_idle_hours', '2');
    $hours = max(1, min(168, $hours));
    if ($start === '' || strtotime($start) === false) {
        return 0;
    }
    $stmt = db()->prepare(
        'SELECT a.`customer_id`, a.`items`, u.`name`, u.`email`, u.`phone`, u.`whatsapp`
         FROM `abandoned_carts` a
         JOIN `customers` c ON c.`id` = a.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id`
         WHERE a.`notified_at` IS NULL
           AND a.`updated_at` >= ?
           AND a.`updated_at` <= DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)
           AND a.`items` NOT IN (\'[]\', \'{}\', \'\')
         ORDER BY a.`updated_at` ASC LIMIT ' . max(1, min(300, (int) $cap))
    );
    $stmt->execute([$start]);
    $n = 0;
    $link = url('cart.php');
    foreach ($stmt->fetchAll() as $r) {
        $cid = (int) $r['customer_id'];
        $queued = notify_send('abandoned_cart', $cid, [
            'link' => $link,
        ], 'You left items in your cart',
            'You still have items in your Oyejo Gas cart. Finish checkout: ' . $link);
        if ($queued > 0) {
            db()->prepare('UPDATE `abandoned_carts` SET `notified_at` = NOW() WHERE `customer_id` = ?')
                ->execute([$cid]);
            $n += $queued;
        }
    }
    return $n;
}

/* ---------------- location suggestions ---------------- */

function loc_suggest($customer_id, $name, $city, $description) {
    $customer_id = (int) $customer_id;
    $name = trim((string) $name);
    $city = trim((string) $city);
    $description = mb_substr(trim((string) $description), 0, 255);
    if ($customer_id < 1) {
        return [false, 'Sign in to suggest a location.'];
    }
    if (strlen($name) < 2 || strlen($name) > 100) {
        return [false, 'Location name must be 2–100 characters.'];
    }
    if (strlen($city) < 2 || strlen($city) > 100) {
        return [false, 'City must be 2–100 characters.'];
    }
    [$ok] = rate_limit('loc_suggest', 'c:' . $customer_id, 8, 86400);
    if (!$ok) {
        return [false, 'Too many suggestions today. Try again tomorrow.'];
    }
    $s = db()->prepare(
        "SELECT 1 FROM `location_suggestions` WHERE `customer_id` = ? AND `name` = ? AND `status` = 'pending' LIMIT 1"
    );
    $s->execute([$customer_id, $name]);
    if ($s->fetchColumn()) {
        return [false, 'You already have a pending suggestion with that name.'];
    }
    db()->prepare(
        'INSERT INTO `location_suggestions` (`customer_id`, `name`, `city`, `description`) VALUES (?, ?, ?, ?)'
    )->execute([$customer_id, $name, $city, $description !== '' ? $description : null]);
    return [true, 'Thanks — we will review “' . $name . '” before it goes live.'];
}

function loc_suggestions($status = 'pending', $limit = 100) {
    $sql = 'SELECT s.*, u.`name` AS customer_name, u.`email`
            FROM `location_suggestions` s
            JOIN `customers` c ON c.`id` = s.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $sql .= ' AND s.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY s.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function loc_my_suggestions($customer_id) {
    $s = db()->prepare('SELECT * FROM `location_suggestions` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 50');
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}

function loc_decide($id, $approve, $actor_id, $note = '', $fee = 0) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `location_suggestions` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return [false, 'Suggestion not found.'];
    }
    if ($row['status'] !== 'pending') {
        return [false, 'That suggestion was already reviewed.'];
    }
    $note = mb_substr(trim((string) $note), 0, 255);
    if (!$approve) {
        $pdo->prepare(
            "UPDATE `location_suggestions` SET `status` = 'rejected', `reviewed_by` = ?, `reviewed_at` = NOW(), `review_note` = ? WHERE `id` = ?"
        )->execute([$actor_id ?: null, $note ?: null, (int) $id]);
        return [true, 'Suggestion rejected.'];
    }
    $fee_minor = (int) round((float) $fee * 100);
    if ($fee_minor < 0 || $fee_minor > 100000000) {
        return [false, 'Delivery fee looks invalid.'];
    }
    $dup = $pdo->prepare('SELECT `id` FROM `delivery_zones` WHERE `name` = ? LIMIT 1');
    $dup->execute([$row['name']]);
    $zid = (int) $dup->fetchColumn();
    if ($zid < 1) {
        $desc = trim((string) ($row['description'] ?? ''));
        $desc = ($desc !== '' ? $desc . ' — ' : '') . $row['city'];
        $pdo->prepare(
            'INSERT INTO `delivery_zones` (`name`, `description`, `fee_minor`, `is_active`, `sort_order`)
             VALUES (?, ?, ?, 1, 50)'
        )->execute([$row['name'], mb_substr($desc, 0, 255), $fee_minor]);
        $zid = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE `delivery_zones` SET `is_active` = 1, `fee_minor` = ? WHERE `id` = ?')
            ->execute([$fee_minor, $zid]);
    }
    $pdo->prepare(
        "UPDATE `location_suggestions` SET `status` = 'approved', `zone_id` = ?, `reviewed_by` = ?, `reviewed_at` = NOW(), `review_note` = ? WHERE `id` = ?"
    )->execute([$zid, $actor_id ?: null, $note ?: null, (int) $id]);
    return [true, 'Location “' . $row['name'] . '” is now live.'];
}
