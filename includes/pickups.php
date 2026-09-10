<?php
/**
 * Oyejo Gas - cylinder pickup / exchange / return engine (Phase 13).
 * Collected empties flow to awaiting_refill (or damaged); delivered fulls are
 * validated company stock and transfer to the customer's hold. Serials are
 * globally unique. Deposits are quoted from size data (charging: Phase 17).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function pickup_flow() {
    return [
        'requested' => ['scheduled', 'cancelled'],
        'scheduled' => ['collected', 'cancelled'],
        'collected' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];
}

function pickup_types() {
    return ['pickup_only' => 'Empty collection', 'exchange' => 'Exchange', 'return' => 'Return'];
}

function pickup_number() {
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $r = '';
    for ($i = 0; $i < 5; $i++) {
        $r .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return 'PCK-' . date('Ymd') . '-' . $r;
}

function pickup_serial_ok($s) {
    return is_string($s) && preg_match('/^[A-Z0-9][A-Z0-9\-]{2,59}$/i', trim($s));
}

function pickup_customer_email($customer_id) {
    $s = db()->prepare(
        'SELECT `u`.`email` FROM `users` `u` JOIN `customers` `c` ON `c`.`user_id` = `u`.`id` WHERE `c`.`id` = ? LIMIT 1'
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchColumn() ?: null;
}

function pickup_notify($r, $event, $subject, $body) {
    $email = pickup_customer_email($r['customer_id']);
    if ($email) {
        notify_emit((int) $r['customer_id'], $event, $email, $subject, $body);
    }
}

/**
 * Customer pickup request. $in: type, size_id, qty, serials, address_id,
 * slot_id, scheduled_date, notes. Returns [row|null, errors].
 */
function pickup_request($customer_id, $user_id, array $in) {
    if (!oyejo_feature('cylinder_pickups')) {
        return [null, ['Pickup requests are currently disabled.']];
    }
    $types = pickup_types();
    $type = (string) ($in['type'] ?? '');
    if (!isset($types[$type])) {
        return [null, ['Choose a valid request type.']];
    }
    if ($type === 'exchange' && !oyejo_feature('cylinder_exchange')) {
        return [null, ['Cylinder exchange is currently disabled.']];
    }
    $customer_id = (int) $customer_id;
    $size_id = (int) ($in['size_id'] ?? 0);
    $s = db()->prepare('SELECT * FROM `cylinder_sizes` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    $s->execute([$size_id]);
    $size = $s->fetch();
    if (!$size) {
        return [null, ['Choose a valid cylinder size.']];
    }
    $qty = (int) ($in['qty'] ?? 0);
    if ($qty < 1 || $qty > 20) {
        return [null, ['Quantity must be 1–20 cylinders.']];
    }
    $serials = [];
    $raw = trim((string) ($in['serials'] ?? ''));
    if ($raw !== '') {
        foreach (preg_split('/[\s,]+/', $raw) as $sn) {
            if ($sn === '') {
                continue;
            }
            if (!pickup_serial_ok($sn)) {
                return [null, ['Serial "' . substr($sn, 0, 30) . '" looks invalid (3–60 letters/digits).']];
            }
            $serials[] = strtoupper(trim($sn));
        }
        if (count($serials) > $qty) {
            return [null, ['List at most ' . $qty . ' serial(s) for this request.']];
        }
    }
    $addr_id = (int) ($in['address_id'] ?? 0);
    $s = db()->prepare('SELECT `id` FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
    $s->execute([$addr_id, $customer_id]);
    if (!$s->fetchColumn()) {
        return [null, ['Choose one of your saved addresses.']];
    }
    $slot_id = (int) ($in['slot_id'] ?? 0);
    $s = db()->prepare('SELECT `id` FROM `delivery_slots` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    $s->execute([$slot_id]);
    if (!$s->fetchColumn()) {
        return [null, ['Choose a valid time slot.']];
    }
    $date = (string) ($in['scheduled_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
        return [null, ['Choose a collection date of today or later.']];
    }
    $notes = substr(trim((string) ($in['notes'] ?? '')), 0, 2000) ?: null;
    if ($serials) {
        $notes = trim(($notes ? $notes . "\n" : '') . 'Customer serials: ' . implode(', ', $serials));
    }
    $deposit = $type === 'exchange' ? (int) $size['deposit_minor'] * $qty : 0;
    for ($i = 0; $i < 5; $i++) {
        try {
            $num = pickup_number();
            db()->prepare(
                'INSERT INTO `pickups` (`pickup_number`, `customer_id`, `type`, `size_id`, `qty`,'
                . " `deposit_minor`, `address_id`, `slot_id`, `scheduled_date`, `status`, `notes`)"
                . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested', ?)"
            )->execute([$num, $customer_id, $type, $size_id, $qty, $deposit, $addr_id, $slot_id, $date, $notes]);
            $id = (int) db()->lastInsertId();
            pickup_notify(['customer_id' => $customer_id], 'pickup_requested', 'Pickup ' . $num . ' requested', $types[$type] . ' (' . $qty . ' × ' . $size['name'] . ') received for ' . $date . '.');
            return [['id' => $id, 'number' => $num, 'deposit' => $deposit], []];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                continue;
            }
            error_log('[oyejo] pickup_request failed: ' . $e->getMessage());
            return [null, ['Could not place your request. Please try again.']];
        }
    }
    return [null, ['Could not place your request. Please try again.']];
}

/** Staff: requested → scheduled with date/slot/driver. Caller checks pickups.manage. */
function pickup_schedule($id, $date, $slot_id, $driver_id) {
    $s = db()->prepare('SELECT * FROM `pickups` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $id]);
    $r = $s->fetch();
    if (!$r || $r['status'] !== 'requested') {
        return [false, 'Only requested pickups can be scheduled.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || $date < date('Y-m-d')) {
        return [false, 'Choose a collection date of today or later.'];
    }
    $s = db()->prepare('SELECT `id` FROM `delivery_slots` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    $s->execute([(int) $slot_id]);
    if (!$s->fetchColumn()) {
        return [false, 'Choose a valid time slot.'];
    }
    $s = db()->prepare("SELECT `id` FROM `drivers` WHERE `id` = ? AND `status` = 'active' LIMIT 1");
    $s->execute([(int) $driver_id]);
    if (!$s->fetchColumn()) {
        return [false, 'Choose an active driver.'];
    }
    db()->prepare("UPDATE `pickups` SET `status` = 'scheduled', `scheduled_date` = ?, `slot_id` = ?, `driver_id` = ? WHERE `id` = ?")
        ->execute([$date, (int) $slot_id, (int) $driver_id, $r['id']]);
    pickup_notify($r, 'pickup_scheduled', 'Pickup ' . $r['pickup_number'] . ' scheduled', 'Collection is scheduled for ' . $date . '.');
    return [true, 'Pickup ' . $r['pickup_number'] . ' scheduled.'];
}

/**
 * Staff: record the collection. $rows: list of [serial, ownership, condition,
 * direction]. Collected units are upserted into the cylinder registry;
 * delivered units must be existing full company stock. Caller checks pickups.manage.
 */
function pickup_collect($id, array $rows) {
    $s = db()->prepare('SELECT * FROM `pickups` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $id]);
    $r = $s->fetch();
    if (!$r || $r['status'] !== 'scheduled') {
        return [false, 'Only scheduled pickups can be collected.'];
    }
    if (!$rows) {
        return [false, 'Record at least one cylinder.'];
    }
    $seen = [];
    foreach ($rows as $i => $row) {
        $sn = strtoupper(trim((string) ($row['serial'] ?? '')));
        $dir = ($row['direction'] ?? '') === 'delivered' ? 'delivered' : 'collected';
        if (!pickup_serial_ok($sn)) {
            return [false, 'Row ' . ($i + 1) . ': invalid serial.'];
        }
        if (isset($seen[$sn . '|' . $dir])) {
            return [false, 'Row ' . ($i + 1) . ': duplicate serial.'];
        }
        $seen[$sn . '|' . $dir] = true;
        $rows[$i]['serial'] = $sn;
        $rows[$i]['direction'] = $dir;
        $rows[$i]['ownership'] = ($row['ownership'] ?? '') === 'company' ? 'company' : 'customer';
        $rows[$i]['condition'] = in_array($row['condition'] ?? '', ['good', 'worn', 'damaged'], true) ? $row['condition'] : null;
        if ($dir === 'collected' && $rows[$i]['condition'] === null) {
            return [false, 'Row ' . ($i + 1) . ': record the collected condition.'];
        }
    }
    try {
        db()->beginTransaction();
        $ins = db()->prepare(
            'INSERT INTO `pickup_cylinders` (`pickup_id`, `cylinder_id`, `serial_snapshot`, `condition_on_collect`, `direction`, `deposit_minor`)'
            . ' VALUES (?, ?, ?, ?, ?, 0)'
        );
        foreach ($rows as $row) {
            $s = db()->prepare('SELECT * FROM `cylinders` WHERE `serial` = ? LIMIT 1 FOR UPDATE');
            $s->execute([$row['serial']]);
            $cyl = $s->fetch();
            if ($row['direction'] === 'delivered') {
                if (!$cyl || $cyl['ownership'] !== 'company' || $cyl['status'] !== 'full') {
                    throw new Exception('Serial ' . $row['serial'] . ' is not full company stock.');
                }
                if ((int) $cyl['size_id'] !== (int) $r['size_id']) {
                    throw new Exception('Serial ' . $row['serial'] . ' is the wrong size.');
                }
                db()->prepare('UPDATE `cylinders` SET `holder_customer_id` = ? WHERE `id` = ?')
                    ->execute([$r['customer_id'], $cyl['id']]);
                $ins->execute([$r['id'], $cyl['id'], $row['serial'], null, 'delivered']);
            } else {
                if ($cyl) {
                    $to = $row['condition'] === 'damaged' ? 'damaged' : 'awaiting_refill';
                    db()->prepare('UPDATE `cylinders` SET `status` = ?, `holder_customer_id` = NULL WHERE `id` = ?')
                        ->execute([$to, $cyl['id']]);
                    $ins->execute([$r['id'], $cyl['id'], $row['serial'], $row['condition'], 'collected']);
                } else {
                    $to = $row['condition'] === 'damaged' ? 'damaged' : 'awaiting_refill';
                    db()->prepare(
                        'INSERT INTO `cylinders` (`serial`, `size_id`, `ownership`, `status`, `holder_customer_id`)'
                        . ' VALUES (?, ?, ?, ?, NULL)'
                    )->execute([$row['serial'], $r['size_id'], $row['ownership'], $to]);
                    $ins->execute([$r['id'], (int) db()->lastInsertId(), $row['serial'], $row['condition'], 'collected']);
                }
            }
        }
        db()->prepare("UPDATE `pickups` SET `status` = 'collected' WHERE `id` = ?")->execute([$r['id']]);
        db()->commit();
        pickup_notify($r, 'pickup_collected', 'Pickup ' . $r['pickup_number'] . ' collected', count($rows) . ' cylinder(s) recorded.');
        return [true, 'Collection recorded (' . count($rows) . ' cylinders).'];
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[oyejo] pickup_collect failed: ' . $e->getMessage());
        return [false, 'Could not record the collection.'];
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return [false, $e->getMessage()];
    }
}

/** Staff: collected → completed. Caller checks pickups.manage. */
function pickup_complete($id) {
    $s = db()->prepare('SELECT * FROM `pickups` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $id]);
    $r = $s->fetch();
    if (!$r || $r['status'] !== 'collected') {
        return [false, 'Only collected pickups can be completed.'];
    }
    db()->prepare("UPDATE `pickups` SET `status` = 'completed', `completed_at` = NOW() WHERE `id` = ?")
        ->execute([$r['id']]);
    pickup_notify($r, 'pickup_completed', 'Pickup ' . $r['pickup_number'] . ' completed', 'Thank you for using Oyejo Gas.');
    return [true, 'Pickup ' . $r['pickup_number'] . ' completed.'];
}

/** Cancel: customer (requested/scheduled) or staff (any open). */
function pickup_cancel($id, $customer_id, $by_staff) {
    $s = db()->prepare('SELECT * FROM `pickups` WHERE `id` = ? LIMIT 1');
    $s->execute([(int) $id]);
    $r = $s->fetch();
    if (!$r) {
        return [false, 'Pickup not found.'];
    }
    if (!$by_staff && (int) $r['customer_id'] !== (int) $customer_id) {
        return [false, 'Pickup not found.'];
    }
    $open = $by_staff
        ? ['requested', 'scheduled', 'collected']
        : ['requested', 'scheduled'];
    if (!in_array($r['status'], $open, true)) {
        return [false, 'This pickup is already ' . $r['status'] . ' and can no longer be cancelled.'];
    }
    db()->prepare("UPDATE `pickups` SET `status` = 'cancelled' WHERE `id` = ?")->execute([$r['id']]);
    pickup_notify($r, 'pickup_cancelled', 'Pickup ' . $r['pickup_number'] . ' cancelled', 'Your pickup ' . $r['pickup_number'] . ' was cancelled.');
    return [true, 'Pickup ' . $r['pickup_number'] . ' cancelled.'];
}

function pickup_get($id) {
    $s = db()->prepare(
        'SELECT `p`.*, `s`.`name` AS `size_name`, `a`.`address_line`, `a`.`city`,'
        . ' `sl`.`name` AS `slot_name`, `u`.`name` AS `driver_name`'
        . ' FROM `pickups` `p` LEFT JOIN `cylinder_sizes` `s` ON `s`.`id` = `p`.`size_id`'
        . ' LEFT JOIN `customer_addresses` `a` ON `a`.`id` = `p`.`address_id`'
        . ' LEFT JOIN `delivery_slots` `sl` ON `sl`.`id` = `p`.`slot_id`'
        . ' LEFT JOIN `drivers` `d` ON `d`.`id` = `p`.`driver_id`'
        . ' LEFT JOIN `users` `u` ON `u`.`id` = `d`.`user_id`'
        . ' WHERE `p`.`id` = ? LIMIT 1'
    );
    $s->execute([(int) $id]);
    $p = $s->fetch();
    if (!$p) {
        return null;
    }
    $s = db()->prepare(
        'SELECT `pc`.*, `c`.`ownership`, `c`.`status` AS `cyl_status` FROM `pickup_cylinders` `pc`'
        . ' LEFT JOIN `cylinders` `c` ON `c`.`id` = `pc`.`cylinder_id`'
        . ' WHERE `pc`.`pickup_id` = ? ORDER BY `pc`.`id`'
    );
    $s->execute([$p['id']]);
    $p['cylinders'] = $s->fetchAll();
    return $p;
}

function pickup_for_customer($customer_id, $limit = 50) {
    $s = db()->prepare(
        'SELECT `p`.*, `s`.`name` AS `size_name` FROM `pickups` `p`'
        . ' LEFT JOIN `cylinder_sizes` `s` ON `s`.`id` = `p`.`size_id`'
        . ' WHERE `p`.`customer_id` = ? ORDER BY `p`.`id` DESC LIMIT ' . (int) $limit
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}

/** Cylinders currently held by a customer (either ownership). */
function cylinders_held($customer_id) {
    $s = db()->prepare(
        'SELECT `c`.*, `s`.`name` AS `size_name` FROM `cylinders` `c`'
        . ' JOIN `cylinder_sizes` `s` ON `s`.`id` = `c`.`size_id`'
        . ' WHERE `c`.`holder_customer_id` = ? ORDER BY `c`.`id` DESC LIMIT 100'
    );
    $s->execute([(int) $customer_id]);
    return $s->fetchAll();
}
