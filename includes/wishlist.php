<?php
/**
 * Oyejo Gas - customer wishlist (saved products).
 * Ownership is always the logged-in customer; guests are sent to login.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

define('WISH_MAX', 50);

function wish_customer_id($user_id) {
    $s = db()->prepare('SELECT `id` FROM `customers` WHERE `user_id` = ? LIMIT 1');
    $s->execute([(int) $user_id]);
    return (int) $s->fetchColumn();
}

function wish_count($customer_id) {
    $s = db()->prepare('SELECT COUNT(*) FROM `wishlists` WHERE `customer_id` = ?');
    $s->execute([(int) $customer_id]);
    return (int) $s->fetchColumn();
}

function wish_has($customer_id, $product_id) {
    $s = db()->prepare('SELECT 1 FROM `wishlists` WHERE `customer_id` = ? AND `product_id` = ? LIMIT 1');
    $s->execute([(int) $customer_id, (int) $product_id]);
    return (bool) $s->fetchColumn();
}

/** Map of product_id => true for this customer (empty on failure). */
function wish_id_set($customer_id) {
    $out = [];
    try {
        $s = db()->prepare('SELECT `product_id` FROM `wishlists` WHERE `customer_id` = ?');
        $s->execute([(int) $customer_id]);
        foreach ($s->fetchAll() as $r) {
            $out[(int) $r['product_id']] = true;
        }
    } catch (Throwable $t) {
        // Table not ready.
    }
    return $out;
}

function wish_list($customer_id) {
    $promo = oyejo_promo_sql('p');
    $s = db()->prepare(
        "SELECT w.`id` AS wish_id, w.`created_at` AS wished_at, p.`id`, p.`slug`, p.`name`, p.`sku`,
                p.`price_minor`, p.`image`, p.`type`, p.`stock_qty`, p.`track_inventory`, p.`is_active`,
                ($promo) AS `promo_now`
         FROM `wishlists` w
         JOIN `products` p ON p.`id` = w.`product_id`
         WHERE w.`customer_id` = ?
         ORDER BY w.`id` DESC LIMIT " . (int) WISH_MAX
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}

/** Returns [ok, message]. */
function wish_add($customer_id, $product_id) {
    $customer_id = (int) $customer_id;
    $product_id = (int) $product_id;
    if ($customer_id < 1 || $product_id < 1) {
        return [false, 'Could not save that product.'];
    }
    $s = db()->prepare('SELECT `id`, `is_active`, `name` FROM `products` WHERE `id` = ? LIMIT 1');
    $s->execute([$product_id]);
    $p = $s->fetch();
    if (!$p || !(int) $p['is_active']) {
        return [false, 'That product is unavailable.'];
    }
    if (wish_has($customer_id, $product_id)) {
        return [true, 'Already on your wishlist.'];
    }
    if (wish_count($customer_id) >= WISH_MAX) {
        return [false, 'Wishlist is full (max ' . WISH_MAX . ' items). Remove something first.'];
    }
    [$ok] = rate_limit('wishlist_add', 'c:' . $customer_id, 40, 3600);
    if (!$ok) {
        return [false, 'Too many wishlist changes. Try again in a bit.'];
    }
    try {
        db()->prepare('INSERT INTO `wishlists` (`customer_id`, `product_id`) VALUES (?, ?)')
            ->execute([$customer_id, $product_id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [true, 'Already on your wishlist.'];
        }
        error_log('[oyejo] wish_add: ' . $e->getMessage());
        return [false, 'Could not save that product. Please try again.'];
    }
    return [true, 'Saved “' . $p['name'] . '” to your wishlist.'];
}

function wish_remove($customer_id, $product_id) {
    $stmt = db()->prepare('DELETE FROM `wishlists` WHERE `customer_id` = ? AND `product_id` = ?');
    $stmt->execute([(int) $customer_id, (int) $product_id]);
    if ($stmt->rowCount() < 1) {
        return [false, 'That item was not on your wishlist.'];
    }
    return [true, 'Removed from your wishlist.'];
}

function wish_clear($customer_id) {
    db()->prepare('DELETE FROM `wishlists` WHERE `customer_id` = ?')->execute([(int) $customer_id]);
    return [true, 'Wishlist cleared.'];
}

function wish_nav_count() {
    static $n = null;
    if ($n !== null) {
        return $n;
    }
    $n = 0;
    if (!is_logged_in()) {
        return 0;
    }
    try {
        $cid = wish_customer_id((int) current_user()['id']);
        $n = $cid > 0 ? wish_count($cid) : 0;
    } catch (Throwable $t) {
        $n = 0;
    }
    return $n;
}

/**
 * Compact add/remove form for shop and product cards.
 * $saved: bool. $next: app-relative path for redirect after POST.
 */
function wish_button($product_id, $saved, $next = '') {
    $next = $next !== '' ? $next : (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $login = url('customer/login.php') . '?next=' . urlencode($next);
    if (!is_logged_in()) {
        return '<p><a class="btn small ghost" href="' . e($login) . '">Save to wishlist</a></p>';
    }
    $html = '<form method="post" action="' . e(url('customer/wishlist.php')) . '" class="inline-form wish-form">';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="action" value="' . ($saved ? 'remove' : 'add') . '">';
    $html .= '<input type="hidden" name="product_id" value="' . (int) $product_id . '">';
    if ($next !== '') {
        $html .= '<input type="hidden" name="next" value="' . e($next) . '">';
    }
    $label = $saved ? 'Saved ✓' : 'Save to wishlist';
    $cls = $saved ? 'btn small ghost' : 'btn small ghost';
    $html .= '<button class="' . $cls . '" type="submit">' . e($label) . '</button></form>';
    return $html;
}

function wish_ensure_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $s = db()->prepare('SELECT 1 FROM `migrations` WHERE `migration` = ?');
        $s->execute(['20260911_wishlists']);
        if ($s->fetchColumn()) {
            return;
        }
    } catch (Throwable $t) {
        return;
    }
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `wishlists` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_wish_customer_product` (`customer_id`, `product_id`),
                KEY `idx_wish_product` (`product_id`),
                CONSTRAINT `fk_wish_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_wish_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->prepare('INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES (?, 2)')
            ->execute(['20260911_wishlists']);
    } catch (Throwable $t) {
        error_log('[oyejo] wish_ensure_schema: ' . $t->getMessage());
    }
}
