<?php
/**
 * Oyejo Gas - refill request engine (Phase 12).
 * Unit prices come from the live catalog (active refill product per size,
 * promo-window aware). The browser only picks size/qty/details/fulfillment.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function refill_statuses() {
    return ['requested', 'assigned', 'processing', 'ready', 'completed', 'cancelled'];
}

/** Lifecycle: allowed next statuses per current status. */
function refill_flow() {
    return [
        'requested' => ['assigned', 'cancelled'],
        'assigned' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];
}

/** Active sizes with live refill unit price (minor units) or NULL if unpriced. */
function refill_sizes() {
    $sizes = db()->query('SELECT * FROM `cylinder_sizes` WHERE `is_active` = 1 ORDER BY `sort_order`')->fetchAll();
    $s = db()->prepare(
        'SELECT `price_minor`,'
        . ' CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`'
        . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())'
        . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())'
        . " THEN `promo_price_minor` END AS `promo_now` FROM `products`"
        . " WHERE `type` = 'refill' AND `size_id` = ? AND `is_active` = 1 ORDER BY `sort_order` LIMIT 1"
    );
    foreach ($sizes as &$z) {
        $s->execute([$z['id']]);
        $p = $s->fetch();
        $z['unit_minor'] = $p ? ($p['promo_now'] !== null ? (int) $p['promo_now'] : (int) $p['price_minor']) : null;
    }
    return $sizes;
}

function refill_number() {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $r = '';
    for ($i = 0; $i < 5; $i++) {
        $r .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return 'RFL-' . date('Ymd') . '-' . $r;
}

function refill_customer_email($customer_id) {
    $s = db()->prepare(
        'SELECT `u`.`email` FROM `users` `u` JOIN `customers` `c` ON `c`.`user_id` = `u`.`id` WHERE `c`.`id` = ? LIMIT 1'
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchColumn() ?: null;
}

/**
 * Place a refill request. $in: size_id, qty, details, fulfillment,
 * address_id, zone_id, slot_id. Returns [row|null, errors].
 */
function refill_request($customer_id, $user_id, array $in) {
    if (!oyejo_feature('gas_refills')) {
        return [null, ['Refill requests are currently disabled.']];
    }
    $customer_id = (int) $customer_id;
    $size_id = (int) ($in['size_id'] ?? 0);
    $qty = (int) ($in['qty'] ?? 0);
    $details = substr(trim((string) ($in['details'] ?? '')), 0, 500) ?: null;
    $fulfillment = ($in['fulfillment'] ?? '') === 'pickup' ? 'pickup' : 'delivery';
    if ($qty < 1 || $qty > 20) {
        return [null, ['Quantity must be 1–20 cylinders.']];
    }
    $unit = null;
    $size_name = '';
    foreach (refill_sizes() as $z) {
        if ((int) $z['id'] === $size_id) {
            $unit = $z['unit_minor'];
            $size_name = $z['name'];
        }
    }
    if ($unit === null) {
        return [null, ['That size is unavailable for refill right now.']];
    }
    $addr_id = null;
    $zone_id = null;
    $slot_id = null;
    if ($fulfillment === 'delivery') {
        $addr_id = (int) ($in['address_id'] ?? 0);
        $s = db()->prepare('SELECT `id` FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
        $s->execute([$addr_id, $customer_id]);
        if (!$s->fetchColumn()) {
            return [null, ['Choose one of your saved addresses for delivery.']];
        }
        $zone_id = (int) ($in['zone_id'] ?? 0);
        $s = db()->prepare('SELECT `id` FROM `delivery_zones` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
        $s->execute([$zone_id]);
        if (!$s->fetchColumn()) {
            return [null, ['Choose a valid delivery zone.']];
        }
        $slot_id = (int) ($in['slot_id'] ?? 0);
        $s = db()->prepare('SELECT `id` FROM `delivery_slots` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
        $s->execute([$slot_id]);
        if (!$s->fetchColumn()) {
            return [null, ['Choose a valid time slot.']];
        }
    }
    $price = $unit * $qty;
    for ($i = 0; $i < 5; $i++) {
        try {
            $num = refill_number();
            db()->prepare(
                'INSERT INTO `refills` (`refill_number`, `customer_id`, `size_id`, `qty`,'
                . ' `customer_cylinder_details`, `fulfillment`, `address_id`, `zone_id`, `slot_id`,'
                . " `status`, `price_minor`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested', ?)"
            )->execute([$num, $customer_id, $size_id, $qty, $details, $fulfillment, $addr_id, $zone_id, $slot_id, $price]);
            $id = (int) db()->lastInsertId();
            $email = refill_customer_email($customer_id);
            if ($email) {
                notify_emit($customer_id, 'refill_requested', $email, 'Refill ' . $num . ' requested', $qty . ' × ' . $size_name . ' refill (' . format_money($price) . ') received. We will confirm shortly.');
            }
            return [['id' => $id, 'number' => $num, 'price' => $price], []];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                continue; // number collision: retry
            }
            error_log('[oyejo] refill_request failed: ' . $e->getMessage());
            return [null, ['Could not place your refill request. Please try again.']];
        }
    }
    return [null, ['Could not place your refill request. Please try again.']];
}

/**
 * Staff status transition with assignment. Caller must check `refills.manage`.
 * $to must be a legal next step; `assigned` requires an active driver id.
 */
function refill_set_status($id, $to, $driver_id = 0) {
    $flow = refill_flow();
    $s = db()->prepare('SELECT * FROM `refills` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $id]);
    $r = $s->fetch();
    if (!$r) {
        return [false, 'Refill not found.'];
    }
    if (!in_array($to, $flow[$r['status']] ?? [], true)) {
        return [false, 'Cannot move a ' . $r['status'] . ' refill to ' . $to . '.'];
    }
    $driver_id = (int) $driver_id;
    if ($to === 'assigned') {
        $d = db()->prepare("SELECT `d`.`id`, `u`.`name` FROM `drivers` `d` JOIN `users` `u` ON `u`.`id` = `d`.`user_id` WHERE `d`.`id` = ? AND `d`.`status` = 'active' LIMIT 1");
        $d->execute([$driver_id]);
        $driver = $d->fetch();
        if (!$driver) {
            return [false, 'Choose an active driver.'];
        }
        db()->prepare("UPDATE `refills` SET `status` = 'assigned', `assigned_driver_id` = ? WHERE `id` = ?")
            ->execute([$driver_id, $r['id']]);
    } elseif ($to === 'completed') {
        db()->prepare("UPDATE `refills` SET `status` = 'completed', `completed_at` = NOW() WHERE `id` = ?")
            ->execute([$r['id']]);
    } else {
        db()->prepare('UPDATE `refills` SET `status` = ? WHERE `id` = ?')->execute([$to, $r['id']]);
    }
    $email = refill_customer_email($r['customer_id']);
    if ($email) {
        $msg = 'Your refill ' . $r['refill_number'] . ' is now ' . $to . '.';
        notify_emit((int) $r['customer_id'], $to === 'cancelled' ? 'refill_cancelled' : 'refill_update', $email, 'Refill ' . $r['refill_number'] . ': ' . $to, $msg);
    }
    return [true, 'Refill ' . $r['refill_number'] . ' → ' . $to . '.'];
}

/** Customer cancellation: only requested/assigned refills. */
function refill_cancel_customer($id, $customer_id) {
    $s = db()->prepare('SELECT * FROM `refills` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
    $s->execute([(int) $id, (int) $customer_id]);
    $r = $s->fetch();
    if (!$r) {
        return [false, 'Refill not found.'];
    }
    if (!in_array($r['status'], ['requested', 'assigned'], true)) {
        return [false, 'This refill is already ' . $r['status'] . ' and can no longer be cancelled.'];
    }
    db()->prepare("UPDATE `refills` SET `status` = 'cancelled' WHERE `id` = ?")->execute([$r['id']]);
    $email = refill_customer_email($customer_id);
    if ($email) {
        notify_emit((int) $customer_id, 'refill_cancelled', $email, 'Refill ' . $r['refill_number'] . ' cancelled', 'Your refill ' . $r['refill_number'] . ' was cancelled.');
    }
    return [true, 'Refill ' . $r['refill_number'] . ' cancelled.'];
}

function refill_get($id) {
    $s = db()->prepare(
        'SELECT `r`.*, `s`.`name` AS `size_name`, `z`.`name` AS `zone_name`, `sl`.`name` AS `slot_name`,'
        . ' `a`.`address_line`, `a`.`city`, `u`.`name` AS `driver_name`'
        . ' FROM `refills` `r` JOIN `cylinder_sizes` `s` ON `s`.`id` = `r`.`size_id`'
        . ' LEFT JOIN `delivery_zones` `z` ON `z`.`id` = `r`.`zone_id`'
        . ' LEFT JOIN `delivery_slots` `sl` ON `sl`.`id` = `r`.`slot_id`'
        . ' LEFT JOIN `customer_addresses` `a` ON `a`.`id` = `r`.`address_id`'
        . ' LEFT JOIN `drivers` `d` ON `d`.`id` = `r`.`assigned_driver_id`'
        . ' LEFT JOIN `users` `u` ON `u`.`id` = `d`.`user_id`'
        . ' WHERE `r`.`id` = ? LIMIT 1'
    );
    $s->execute([(int) $id]);
    return $s->fetch() ?: null;
}

function refill_for_customer($customer_id, $limit = 50) {
    $s = db()->prepare(
        'SELECT `r`.*, `s`.`name` AS `size_name` FROM `refills` `r`'
        . ' JOIN `cylinder_sizes` `s` ON `s`.`id` = `r`.`size_id`'
        . ' WHERE `r`.`customer_id` = ? ORDER BY `r`.`id` DESC LIMIT ' . (int) $limit
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}
