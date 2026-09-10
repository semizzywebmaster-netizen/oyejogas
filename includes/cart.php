<?php
/**
 * Oyejo Gas - cart + checkout engine (Phase 9).
 * The session cart stores ONLY product_id => qty. Prices, promos, stock and
 * coupons are re-read from the database on every use; the browser is never
 * trusted with money. Promo windows use SQL NOW() (no PHP/DB skew).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

define('OYEJO_CART_KEY', 'oyejo_cart');
define('OYEJO_LAST_ORDER_KEY', 'oyejo_last_order');

function cart_get() {
    $c = $_SESSION[OYEJO_CART_KEY] ?? [];
    return is_array($c) ? $c : [];
}

function cart_count() {
    return (int) array_sum(cart_get());
}

function cart_clear() {
    unset($_SESSION[OYEJO_CART_KEY]);
}

/** Live promo minor units for a product id, or NULL. SQL-side window. */
function cart_live_promo($id) {
    $s = db()->prepare(
        'SELECT CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`'
        . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())'
        . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())'
        . ' THEN `promo_price_minor` END AS `p` FROM `products` WHERE `id` = ? LIMIT 1'
    );
    $s->execute([(int) $id]);
    $v = $s->fetchColumn();
    return $v === false || $v === null ? null : (int) $v;
}

function cart_add($product_id, $qty) {
    if (!oyejo_feature('product_ordering')) {
        return [false, 'Ordering is currently disabled.'];
    }
    $id = (int) $product_id;
    $qty = max(1, min(99, (int) $qty));
    $s = db()->prepare('SELECT `stock_qty`, `track_inventory`, `is_active` FROM `products` WHERE `id` = ? LIMIT 1');
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r || empty($r['is_active'])) {
        return [false, 'That product is unavailable.'];
    }
    if (!empty($r['track_inventory']) && (int) $r['stock_qty'] <= 0) {
        return [false, 'That product is out of stock.'];
    }
    $cart = cart_get();
    $new = min(99, ($cart[$id] ?? 0) + $qty);
    if (!empty($r['track_inventory']) && $new > (int) $r['stock_qty']) {
        return [false, 'Only ' . (int) $r['stock_qty'] . ' left in stock.'];
    }
    $cart[$id] = $new;
    $_SESSION[OYEJO_CART_KEY] = $cart;
    return [true, 'Added to cart.'];
}

function cart_set_qty($product_id, $qty) {
    $id = (int) $product_id;
    $qty = max(0, min(99, (int) $qty));
    $cart = cart_get();
    if ($qty === 0 || !isset($cart[$id])) {
        unset($cart[$id]);
        $_SESSION[OYEJO_CART_KEY] = $cart;
        return [true, 'Removed from cart.'];
    }
    $s = db()->prepare('SELECT `stock_qty`, `track_inventory`, `is_active` FROM `products` WHERE `id` = ? LIMIT 1');
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r || empty($r['is_active'])) {
        unset($cart[$id]);
        $_SESSION[OYEJO_CART_KEY] = $cart;
        return [false, 'That product is unavailable and was removed.'];
    }
    if (!empty($r['track_inventory']) && $qty > (int) $r['stock_qty']) {
        return [false, 'Only ' . (int) $r['stock_qty'] . ' left in stock.'];
    }
    $cart[$id] = $qty;
    $_SESSION[OYEJO_CART_KEY] = $cart;
    return [true, 'Cart updated.'];
}

/**
 * Resolve the cart to live lines. Self-heals the session (drops inactive /
 * out-of-stock rows, caps over-stock qty). Returns [lines, notices].
 */
function cart_lines() {
    $cart = cart_get();
    $lines = [];
    $notices = [];
    if (!$cart) {
        return [$lines, $notices];
    }
    $ids = array_map('intval', array_keys($cart));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare(
        'SELECT `id`, `slug`, `name`, `price_minor`, `stock_qty`, `track_inventory`, `is_active`,'
        . ' CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`'
        . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())'
        . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())'
        . ' THEN `promo_price_minor` END AS `promo_now`'
        . " FROM `products` WHERE `id` IN ($ph)"
    );
    $s->execute($ids);
    $rows = [];
    foreach ($s->fetchAll() as $r) {
        $rows[(int) $r['id']] = $r;
    }
    $fixed = [];
    foreach ($cart as $id => $qty) {
        $id = (int) $id;
        $qty = max(1, min(99, (int) $qty));
        $r = $rows[$id] ?? null;
        if (!$r || empty($r['is_active'])) {
            $notices[] = 'An unavailable item was removed from your cart.';
            continue;
        }
        if (!empty($r['track_inventory']) && (int) $r['stock_qty'] <= 0) {
            $notices[] = '"' . $r['name'] . '" is out of stock and was removed.';
            continue;
        }
        if (!empty($r['track_inventory']) && $qty > (int) $r['stock_qty']) {
            $qty = (int) $r['stock_qty'];
            $notices[] = '"' . $r['name'] . '" was capped to available stock.';
        }
        $unit = $r['promo_now'] !== null ? (int) $r['promo_now'] : (int) $r['price_minor'];
        $lines[] = [
            'id' => $id, 'slug' => $r['slug'], 'name' => $r['name'], 'qty' => $qty,
            'unit' => $unit, 'total' => $unit * $qty,
            'stock' => (int) $r['stock_qty'], 'track' => !empty($r['track_inventory']),
        ];
        $fixed[$id] = $qty;
    }
    $_SESSION[OYEJO_CART_KEY] = $fixed;
    return [$lines, array_unique($notices)];
}

function cart_subtotal(array $lines) {
    $t = 0;
    foreach ($lines as $l) {
        $t += $l['total'];
    }
    return $t;
}

/** Validate a coupon code against a subtotal. Returns [row|null, discount, error]. */
function cart_coupon($code, $subtotal) {
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return [null, 0, ''];
    }
    if (!oyejo_feature('coupons')) {
        return [null, 0, 'Coupons are currently disabled.'];
    }
    $s = db()->prepare(
        'SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1'
        . ' AND (`starts_at` IS NULL OR `starts_at` <= NOW())'
        . ' AND (`ends_at` IS NULL OR `ends_at` >= NOW()) LIMIT 1'
    );
    $s->execute([$code]);
    $c = $s->fetch();
    if (!$c) {
        return [null, 0, 'Invalid or expired coupon code.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return [null, 0, 'That coupon has reached its usage limit.'];
    }
    if ($subtotal < (int) $c['min_order_minor']) {
        return [null, 0, 'That coupon needs a minimum order of ' . format_money((int) $c['min_order_minor']) . '.'];
    }
    $d = $c['type'] === 'percent'
        ? (int) floor($subtotal * (int) $c['value'] / 100)
        : (int) $c['value'];
    if ($c['max_discount_minor'] !== null) {
        $d = min($d, (int) $c['max_discount_minor']);
    }
    return [$c, min($d, $subtotal), ''];
}

function cart_zones() {
    return db()->query('SELECT * FROM `delivery_zones` WHERE `is_active` = 1 ORDER BY `sort_order`')->fetchAll();
}

function cart_slots() {
    return db()->query('SELECT * FROM `delivery_slots` WHERE `is_active` = 1 ORDER BY `sort_order`')->fetchAll();
}

function cart_fee(array $zone, $subtotal) {
    $free = (int) ($zone['free_above_minor'] ?? 0);
    if ($free > 0 && $subtotal >= $free) {
        return 0;
    }
    return (int) $zone['fee_minor'];
}

function cart_payment_methods() {
    return [
        'wallet'   => ['label' => 'Wallet (pay now)', 'toggle' => 'customer_wallet'],
        'cod'      => ['label' => 'Cash on delivery', 'toggle' => 'cash_on_delivery'],
        'transfer' => ['label' => 'Bank transfer', 'toggle' => 'bank_transfers'],
        'online'   => ['label' => 'Pay online', 'toggle' => 'online_payments'],
    ];
}

/** Fetch or create the customers row for a user. Returns customer_id (0 on failure). */
function cart_customer_id($user_id) {
    $user_id = (int) $user_id;
    $s = db()->prepare('SELECT `id` FROM `customers` WHERE `user_id` = ? LIMIT 1');
    $s->execute([$user_id]);
    $id = $s->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    for ($i = 0; $i < 5; $i++) {
        try {
            $cc = 'CUST-' . $user_id . '-' . random_int(100, 999);
            $rc = 'REF-' . strtoupper(substr(md5($user_id . '.' . random_int(1, 999999)), 0, 8));
            db()->prepare('INSERT INTO `customers` (`user_id`, `customer_code`, `referral_code`) VALUES (?, ?, ?)')
                ->execute([$user_id, $cc, $rc]);
            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            continue; // code collision: retry
        }
    }
    return 0;
}

function cart_order_number() {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $rand = '';
    for ($i = 0; $i < 5; $i++) {
        $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'OY-' . date('Ymd') . '-' . $rand;
}

/**
 * Place an order. All money re-derived server-side. Returns [order|null, errors].
 * $in: user_id, customer_id, use_address_id, addr[], zone_id, slot_id, coupon, method, notes.
 */
function cart_place_order(array $in) {
    if (!oyejo_feature('product_ordering')) {
        return [null, ['Ordering is currently disabled.']];
    }
    list($lines, $notices) = cart_lines();
    if (!$lines) {
        return [null, ['Your cart is empty.']];
    }
    $subtotal = cart_subtotal($lines);
    list($coupon, $discount, $cerr) = cart_coupon($in['coupon'] ?? '', $subtotal);
    if ($cerr !== '') {
        return [null, [$cerr]];
    }
    $methods = cart_payment_methods();
    $method = (string) ($in['method'] ?? '');
    if (!isset($methods[$method]) || !oyejo_feature($methods[$method]['toggle'])) {
        return [null, ['That payment method is unavailable.']];
    }
    $s = db()->prepare('SELECT * FROM `delivery_zones` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    $s->execute([(int) ($in['zone_id'] ?? 0)]);
    $zone = $s->fetch();
    if (!$zone) {
        return [null, ['Choose a valid delivery zone.']];
    }
    $s = db()->prepare('SELECT `id` FROM `delivery_slots` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    $s->execute([(int) ($in['slot_id'] ?? 0)]);
    if (!$s->fetchColumn()) {
        return [null, ['Choose a valid delivery time slot.']];
    }
    $fee = cart_fee($zone, $subtotal);
    $total = max(0, $subtotal - $discount + $fee);
    $customer_id = (int) $in['customer_id'];
    $user_id = (int) $in['user_id'];

    // Address: saved (ownership-checked) or new (validated + stored).
    $addr_id = (int) ($in['use_address_id'] ?? 0);
    $addr = null;
    if ($addr_id > 0) {
        $s = db()->prepare('SELECT * FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
        $s->execute([$addr_id, $customer_id]);
        $addr = $s->fetch();
        if (!$addr) {
            return [null, ['That saved address was not found.']];
        }
    } else {
        $a = $in['addr'] ?? [];
        $recipient = trim((string) ($a['recipient'] ?? ''));
        $phone = trim((string) ($a['phone'] ?? ''));
        $line = trim((string) ($a['line'] ?? ''));
        $city = trim((string) ($a['city'] ?? ''));
        if (strlen($recipient) < 2 || strlen($recipient) > 150) {
            return [null, ['Recipient name must be 2–150 characters.']];
        }
        if (!preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
            return [null, ['Delivery phone number looks invalid.']];
        }
        if (strlen($line) < 5 || strlen($line) > 255) {
            return [null, ['Street address must be 5–255 characters.']];
        }
        if (strlen($city) < 2 || strlen($city) > 100) {
            return [null, ['City must be 2–100 characters.']];
        }
        $addr = [
            'recipient_name' => $recipient, 'phone' => $phone, 'address_line' => $line,
            'city' => $city, 'state' => 'Lagos',
            'landmark' => substr(trim((string) ($a['landmark'] ?? '')), 0, 255) ?: null,
            'new' => true,
        ];
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            db()->beginTransaction();
            // 1. Lock + recheck stock, decrement tracked lines.
            foreach ($lines as $l) {
                if (!$l['track']) {
                    continue;
                }
                $s = db()->prepare('SELECT `stock_qty` FROM `products` WHERE `id` = ? FOR UPDATE');
                $s->execute([$l['id']]);
                $stock = $s->fetchColumn();
                if ($stock === false || (int) $stock < $l['qty']) {
                    throw new Exception('"' . $l['name'] . '" no longer has enough stock.');
                }
                db()->prepare('UPDATE `products` SET `stock_qty` = `stock_qty` - ? WHERE `id` = ?')
                    ->execute([$l['qty'], $l['id']]);
            }
            // 2. Consume coupon (race-safe).
            $coupon_id = null;
            if ($coupon) {
                $s = db()->prepare(
                    'UPDATE `coupons` SET `used_count` = `used_count` + 1 WHERE `id` = ?'
                    . ' AND (`usage_limit` IS NULL OR `used_count` < `usage_limit`)'
                );
                $s->execute([$coupon['id']]);
                if ($s->rowCount() !== 1) {
                    throw new Exception('That coupon just ran out. Remove it and try again.');
                }
                $coupon_id = (int) $coupon['id'];
            }
            // 3. Store new address.
            if (!empty($addr['new'])) {
                $s = db()->prepare('SELECT COUNT(*) FROM `customer_addresses` WHERE `customer_id` = ?');
                $s->execute([$customer_id]);
                $is_default = $s->fetchColumn() == 0 ? 1 : 0;
                db()->prepare(
                    'INSERT INTO `customer_addresses` (`customer_id`, `label`, `recipient_name`, `phone`,'
                    . ' `address_line`, `city`, `state`, `landmark`, `zone_id`, `is_default`)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $customer_id, 'Home', $addr['recipient_name'], $addr['phone'],
                    $addr['address_line'], $addr['city'], $addr['state'], $addr['landmark'],
                    $zone['id'], $is_default,
                ]);
            }
            $snapshot = $addr['recipient_name'] . ', ' . $addr['address_line'] . ', '
                . $addr['city'] . ', ' . ($addr['state'] ?? 'Lagos')
                . (!empty($addr['landmark']) ? ' (' . $addr['landmark'] . ')' : '')
                . ' — ' . $zone['name'];
            // 4. Order first: posted wallet rows are immutable (trigger), so the
            // wallet entry must carry its order link at INSERT time.
            $number = cart_order_number();
            db()->prepare(
                'INSERT INTO `orders` (`order_number`, `customer_id`, `status`, `subtotal_minor`,'
                . ' `delivery_fee_minor`, `discount_minor`, `total_minor`, `payment_status`, `payment_method`,'
                . ' `zone_id`, `slot_id`, `address_text`, `delivery_phone`, `coupon_id`, `notes`)'
                . ' VALUES (?, ?, \'pending\', ?, ?, ?, ?, \'unpaid\', ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $number, $customer_id, $subtotal, $fee, $discount, $total, $method,
                $zone['id'], (int) $in['slot_id'], substr($snapshot, 0, 500), $addr['phone'],
                $coupon_id, substr(trim((string) ($in['notes'] ?? '')), 0, 2000) ?: null,
            ]);
            $order_id = (int) db()->lastInsertId();
            // 5. Wallet debit (locked, balance-checked), linked at insert.
            if ($method === 'wallet') {
                $s = db()->prepare('SELECT `id`, `balance_minor`, `status` FROM `wallets` WHERE `customer_id` = ? FOR UPDATE');
                $s->execute([$customer_id]);
                $w = $s->fetch();
                if (!$w) {
                    throw new Exception('You have no wallet yet. Choose another payment method.');
                }
                if ($w['status'] !== 'active') {
                    throw new Exception('Your wallet is frozen. Choose another payment method.');
                }
                if ((int) $w['balance_minor'] < $total) {
                    throw new Exception('Insufficient wallet balance for this order.');
                }
                $new_bal = (int) $w['balance_minor'] - $total;
                db()->prepare(
                    'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
                    . ' `amount_minor`, `balance_after_minor`, `status`, `related_type`, `related_id`,'
                    . ' `narration`, `created_by`)'
                    . ' VALUES (?, ?, \'payment\', \'debit\', ?, ?, \'completed\', \'order\', ?, ?, ?)'
                )->execute([
                    $w['id'], 'PAY-' . $number, $total, $new_bal, $order_id,
                    'Order ' . $number, $user_id ?: null,
                ]);
                db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
                    ->execute([$new_bal, $w['id']]);
                db()->prepare('UPDATE `orders` SET `payment_status` = \'paid\' WHERE `id` = ?')
                    ->execute([$order_id]);
            }
            // 5b. Finance rows (Phase 17): payment record + persistent invoice.
            $wallet_txn_id = 0;
            if ($method === 'wallet') {
                $s = db()->prepare('SELECT `id` FROM `wallet_transactions` WHERE `related_type` = \'order\' AND `related_id` = ? LIMIT 1');
                $s->execute([$order_id]);
                $wallet_txn_id = (int) $s->fetchColumn();
            }
            require_once BASE_PATH . '/includes/payments.php';
            pay_record_checkout($order_id, $customer_id, $method, $total, $wallet_txn_id);
            // 6. Items + history.
            $item = db()->prepare(
                'INSERT INTO `order_items` (`order_id`, `product_id`, `name`, `qty`, `unit_price_minor`, `total_minor`)'
                . ' VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($lines as $l) {
                $item->execute([$order_id, $l['id'], $l['name'], $l['qty'], $l['unit'], $l['total']]);
            }
            db()->prepare(
                'INSERT INTO `order_status_history` (`order_id`, `from_status`, `to_status`, `changed_by`, `note`)'
                . ' VALUES (?, NULL, \'pending\', ?, ?)'
            )->execute([$order_id, $user_id ?: null, 'Order placed (' . $method . ')']);
            db()->commit();
            cart_clear();
            return [['id' => $order_id, 'number' => $number, 'total' => $total, 'method' => $method], []];
        } catch (PDOException $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            // Order-number collision: retry with a fresh number.
            if ($e->getCode() === '23000' && stripos($e->getMessage(), 'uq_orders_number') !== false) {
                continue;
            }
            error_log('cart_place_order: ' . $e->getMessage());
            return [null, ['Could not place your order. Please try again.']];
        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            return [null, [$e->getMessage()]];
        }
    }
    return [null, ['Could not place your order. Please try again.']];
}

/**
 * Cancel an owned order. Rules: status must be pending/confirmed; restocks
 * tracked lines, releases the coupon, refunds wallet-paid orders to the wallet.
 */
function order_cancel($order_id, $customer_id, $actor_uid, $reason = '') {
    $order_id = (int) $order_id;
    $customer_id = (int) $customer_id;
    try {
        db()->beginTransaction();
        $s = db()->prepare('SELECT * FROM `orders` WHERE `id` = ? AND `customer_id` = ? FOR UPDATE');
        $s->execute([$order_id, $customer_id]);
        $o = $s->fetch();
        if (!$o) {
            throw new Exception('Order not found.');
        }
        if (!in_array($o['status'], ['pending', 'confirmed'], true)) {
            throw new Exception('This order is already ' . $o['status'] . ' and can no longer be cancelled.');
        }
        $reason = substr(trim((string) $reason), 0, 255) ?: 'Cancelled by customer';
        db()->prepare('UPDATE `orders` SET `status` = \'cancelled\', `cancelled_reason` = ?, `cancelled_at` = NOW() WHERE `id` = ?')
            ->execute([$reason, $order_id]);
        db()->prepare(
            'INSERT INTO `order_status_history` (`order_id`, `from_status`, `to_status`, `changed_by`, `note`)'
            . ' VALUES (?, ?, \'cancelled\', ?, ?)'
        )->execute([$order_id, $o['status'], $actor_uid ?: null, $reason]);
        // Restock tracked lines.
        $items = db()->prepare(
            'SELECT `oi`.`product_id`, `oi`.`qty`, `p`.`track_inventory` FROM `order_items` `oi`'
            . ' JOIN `products` `p` ON `p`.`id` = `oi`.`product_id` WHERE `oi`.`order_id` = ?'
        );
        $items->execute([$order_id]);
        foreach ($items->fetchAll() as $it) {
            if (!empty($it['track_inventory'])) {
                db()->prepare('UPDATE `products` SET `stock_qty` = `stock_qty` + ? WHERE `id` = ?')
                    ->execute([$it['qty'], $it['product_id']]);
            }
        }
        // Release coupon.
        if (!empty($o['coupon_id'])) {
            db()->prepare('UPDATE `coupons` SET `used_count` = GREATEST(0, `used_count` - 1) WHERE `id` = ?')
                ->execute([$o['coupon_id']]);
        }
        // Refund wallet-paid orders.
        if ($o['payment_status'] === 'paid' && $o['payment_method'] === 'wallet') {
            $s = db()->prepare('SELECT `id`, `balance_minor` FROM `wallets` WHERE `customer_id` = ? FOR UPDATE');
            $s->execute([$customer_id]);
            $w = $s->fetch();
            if (!$w) {
                throw new Exception('Wallet missing; contact support for your refund.');
            }
            $new_bal = (int) $w['balance_minor'] + (int) $o['total_minor'];
            db()->prepare(
                'INSERT INTO `wallet_transactions` (`wallet_id`, `reference`, `type`, `direction`,'
                . ' `amount_minor`, `balance_after_minor`, `status`, `related_type`, `related_id`,'
                . ' `narration`, `created_by`)'
                . ' VALUES (?, ?, \'refund\', \'credit\', ?, ?, \'completed\', \'order\', ?, ?, ?)'
            )->execute([
                $w['id'], 'RF-' . $o['order_number'], (int) $o['total_minor'], $new_bal,
                $order_id, 'Refund ' . $o['order_number'], $actor_uid ?: null,
            ]);
            db()->prepare('UPDATE `wallets` SET `balance_minor` = ? WHERE `id` = ?')
                ->execute([$new_bal, $w['id']]);
            db()->prepare('UPDATE `orders` SET `payment_status` = \'refunded\' WHERE `id` = ?')
                ->execute([$order_id]);
            db()->prepare("UPDATE `payments` SET `status` = 'refunded' WHERE `order_id` = ? AND `method` = 'wallet'")
                ->execute([$order_id]);
        }
        require_once BASE_PATH . '/includes/payments.php';
        pay_invoice_sync($order_id);
        db()->commit();
        return [true, 'Order ' . $o['order_number'] . ' cancelled.'];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('order_cancel: ' . $e->getMessage());
        return [false, 'Could not cancel the order. Please try again.'];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [false, $e->getMessage()];
    }
}
