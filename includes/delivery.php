<?php
/**
 * Oyejo Gas - dispatch, deliveries, zones, slots and driver cash (Phase 16).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function del_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'delivery', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function del_flow() {
    return [
        'pending' => ['assigned', 'cancelled'],
        'assigned' => ['out_for_delivery', 'pending', 'cancelled'],
        'out_for_delivery' => ['delivered', 'failed'],
        'failed' => ['pending', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];
}

/* ---------------- zones & slots (DL-06…DL-08) ---------------- */

function del_zones($active_only = false) {
    $sql = 'SELECT * FROM `delivery_zones`';
    if ($active_only) {
        $sql .= ' WHERE `is_active` = 1';
    }
    $sql .= ' ORDER BY `sort_order`, `name`';
    return db()->query($sql)->fetchAll();
}

function del_zone_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $desc = mb_substr(trim((string) ($data['description'] ?? '')), 0, 255);
    $fee = (int) round((float) ($data['fee'] ?? 0) * 100);
    $free_above = trim((string) ($data['free_above'] ?? '')) === ''
        ? null : (int) round((float) $data['free_above'] * 100);
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    if ($name === '' || mb_strlen($name) > 100) {
        return [false, 'Zone name is required (max 100 characters).'];
    }
    if ($fee < 0 || $fee > 100000000) {
        return [false, 'Delivery fee looks invalid.'];
    }
    if ($free_above !== null && ($free_above < 0 || $free_above > 1000000000)) {
        return [false, 'Free-delivery threshold looks invalid.'];
    }
    $pdo = db();
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `delivery_zones` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Zone not found.'];
        }
        $pdo->prepare(
            'UPDATE `delivery_zones` SET `name` = ?, `description` = ?, `fee_minor` = ?,
             `free_above_minor` = ?, `is_active` = ?, `sort_order` = ? WHERE `id` = ?'
        )->execute([$name, $desc ?: null, $fee, $free_above, $active, $sort, (int) $id]);
        del_audit('delivery.zone_update', $actor_id, (int) $id, null, ['name' => $name]);
        return [true, 'Zone saved.'];
    }
    $pdo->prepare(
        'INSERT INTO `delivery_zones` (`name`, `description`, `fee_minor`, `free_above_minor`, `is_active`, `sort_order`)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, $desc ?: null, $fee, $free_above, $active, $sort]);
    del_audit('delivery.zone_create', $actor_id, (int) $pdo->lastInsertId(), null, ['name' => $name]);
    return [true, 'Zone created.'];
}

function del_zone_delete($id, $actor_id) {
    $pdo = db();
    foreach (['deliveries' => 'zone_id', 'orders' => 'zone_id'] as $tbl => $col) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $stmt->execute([(int) $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return [false, 'That zone is used by ' . $tbl . ' — deactivate it instead.'];
        }
    }
    $stmt = $pdo->prepare('DELETE FROM `delivery_zones` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->rowCount()) {
        return [false, 'Zone not found.'];
    }
    del_audit('delivery.zone_delete', $actor_id, (int) $id, null, null);
    return [true, 'Zone deleted.'];
}

function del_slots($active_only = false) {
    $sql = 'SELECT * FROM `delivery_slots`';
    if ($active_only) {
        $sql .= ' WHERE `is_active` = 1';
    }
    $sql .= ' ORDER BY `sort_order`, `window_start`';
    return db()->query($sql)->fetchAll();
}

function del_slot_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $start = trim((string) ($data['window_start'] ?? ''));
    $end = trim((string) ($data['window_end'] ?? ''));
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    if ($name === '' || mb_strlen($name) > 100) {
        return [false, 'Slot name is required (max 100 characters).'];
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
        return [false, 'Slot windows must be HH:MM times.'];
    }
    if (strtotime($end) <= strtotime($start)) {
        return [false, 'Window end must be after window start.'];
    }
    $pdo = db();
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `delivery_slots` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Slot not found.'];
        }
        $pdo->prepare(
            'UPDATE `delivery_slots` SET `name` = ?, `window_start` = ?, `window_end` = ?,
             `is_active` = ?, `sort_order` = ? WHERE `id` = ?'
        )->execute([$name, $start, $end, $active, $sort, (int) $id]);
        del_audit('delivery.slot_update', $actor_id, (int) $id, null, ['name' => $name]);
        return [true, 'Slot saved.'];
    }
    $pdo->prepare(
        'INSERT INTO `delivery_slots` (`name`, `window_start`, `window_end`, `is_active`, `sort_order`)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$name, $start, $end, $active, $sort]);
    del_audit('delivery.slot_create', $actor_id, (int) $pdo->lastInsertId(), null, ['name' => $name]);
    return [true, 'Slot created.'];
}

function del_slot_delete($id, $actor_id) {
    $pdo = db();
    foreach (['deliveries' => 'slot_id', 'orders' => 'slot_id'] as $tbl => $col) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $stmt->execute([(int) $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return [false, 'That slot is used by ' . $tbl . ' — deactivate it instead.'];
        }
    }
    $stmt = $pdo->prepare('DELETE FROM `delivery_slots` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->rowCount()) {
        return [false, 'Slot not found.'];
    }
    del_audit('delivery.slot_delete', $actor_id, (int) $id, null, null);
    return [true, 'Slot deleted.'];
}

/* ---------------- deliveries ---------------- */

function del_number($pdo) {
    for ($i = 0; $i < 5; $i++) {
        $n = 'DLV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt = $pdo->prepare('SELECT 1 FROM `deliveries` WHERE `delivery_number` = ?');
        $stmt->execute([$n]);
        if (!$stmt->fetchColumn()) {
            return $n;
        }
    }
    return 'DLV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
}

function del_open_for_ref($kind, $ref_id) {
    $col = ['order' => 'order_id', 'refill' => 'refill_id', 'pickup' => 'pickup_id'][$kind] ?? null;
    if (!$col) {
        return false;
    }
    $stmt = db()->prepare(
        "SELECT 1 FROM `deliveries` WHERE `$col` = ? AND `status` NOT IN ('delivered','cancelled') LIMIT 1"
    );
    $stmt->execute([(int) $ref_id]);
    return (bool) $stmt->fetchColumn();
}

/** Create a delivery leg for an order, refill or pickup (DL-04, DL-05). */
function del_create($kind, $ref_number, $zone_id, $slot_id, $instructions, $actor_id) {
    $ref_number = trim((string) $ref_number);
    if ($ref_number === '') {
        return [false, 'Reference number is required.'];
    }
    $pdo = db();
    $order_id = $refill_id = $pickup_id = null;
    $expected = 0;
    if ($kind === 'order') {
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE `order_number` = ?');
        $stmt->execute([$ref_number]);
        $ref = $stmt->fetch();
        if (!$ref) {
            return [false, 'Order not found.'];
        }
        if (!in_array($ref['status'], ['confirmed', 'preparing'], true)) {
            return [false, 'Only confirmed or preparing orders can be dispatched.'];
        }
        $order_id = (int) $ref['id'];
        if ($ref['payment_status'] !== 'paid') {
            $expected = (int) $ref['total_minor'];
        }
        $zone_id = $zone_id > 0 ? (int) $zone_id : ($ref['zone_id'] ? (int) $ref['zone_id'] : null);
        $slot_id = $slot_id > 0 ? (int) $slot_id : ($ref['slot_id'] ? (int) $ref['slot_id'] : null);
        if (trim((string) $instructions) === '' && trim((string) ($ref['notes'] ?? '')) !== '') {
            $instructions = $ref['notes'];
        }
    } elseif ($kind === 'refill') {
        $stmt = $pdo->prepare('SELECT * FROM `refills` WHERE `refill_number` = ?');
        $stmt->execute([$ref_number]);
        $ref = $stmt->fetch();
        if (!$ref) {
            return [false, 'Refill not found.'];
        }
        if (!in_array($ref['status'], ['processing', 'ready'], true)) {
            return [false, 'Only processing or ready refills can be dispatched.'];
        }
        $refill_id = (int) $ref['id'];
    } elseif ($kind === 'pickup') {
        $stmt = $pdo->prepare('SELECT * FROM `pickups` WHERE `pickup_number` = ?');
        $stmt->execute([$ref_number]);
        $ref = $stmt->fetch();
        if (!$ref) {
            return [false, 'Pickup not found.'];
        }
        if ($ref['status'] !== 'scheduled') {
            return [false, 'Only scheduled pickups can be dispatched.'];
        }
        $pickup_id = (int) $ref['id'];
    } else {
        return [false, 'Unknown delivery kind.'];
    }
    $ref_id = $order_id ?: ($refill_id ?: $pickup_id);
    if (del_open_for_ref($kind, $ref_id)) {
        return [false, 'That reference already has an open delivery.'];
    }
    foreach (['zone_id' => 'delivery_zones', 'slot_id' => 'delivery_slots'] as $k => $tbl) {
        $$k = (int) $$k;
        if ($$k > 0) {
            $stmt = $pdo->prepare("SELECT 1 FROM `$tbl` WHERE `id` = ? AND `is_active` = 1");
            $stmt->execute([$$k]);
            if (!$stmt->fetchColumn()) {
                return [false, 'Choose an active ' . str_replace('_', ' ', $k) . '.'];
            }
        } else {
            $$k = null;
        }
    }
    $instructions = mb_substr(trim((string) $instructions), 0, 500);
    try {
        $pdo->beginTransaction();
        $number = del_number($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO `deliveries` (`delivery_number`, `order_id`, `refill_id`, `pickup_id`,
             `zone_id`, `slot_id`, `status`, `customer_instructions`, `cash_expected_minor`)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, ?)'
        );
        $stmt->execute([$number, $order_id, $refill_id, $pickup_id, $zone_id, $slot_id,
            $instructions ?: null, $expected]);
        $did = (int) $pdo->lastInsertId();
        del_audit('delivery.create', $actor_id, $did, null,
            ['number' => $number, 'kind' => $kind, 'ref' => $ref_number]);
        $pdo->commit();
        return [true, 'Delivery ' . $number . ' queued for dispatch.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('del_create: ' . $e->getMessage());
        return [false, 'Could not create the delivery. Please try again.'];
    }
}

function del_get($id) {
    $stmt = db()->prepare(
        'SELECT d.*, dr.`driver_code`, dr.`user_id` AS driver_user_id,
                z.`name` AS zone_name, s.`name` AS slot_name,
                o.`order_number`, o.`status` AS order_status, o.`address_text`, o.`delivery_phone`,
                o.`total_minor` AS order_total, o.`payment_status`,
                r.`refill_number`, r.`status` AS refill_status,
                p.`pickup_number`, p.`status` AS pickup_status, p.`type` AS pickup_type,
                cu.`id` AS customer_id, u.`name` AS customer_name, u.`email` AS customer_email, u.`phone` AS customer_phone
         FROM `deliveries` d
         LEFT JOIN `drivers` dr ON dr.`id` = d.`driver_id`
         LEFT JOIN `delivery_zones` z ON z.`id` = d.`zone_id`
         LEFT JOIN `delivery_slots` s ON s.`id` = d.`slot_id`
         LEFT JOIN `orders` o ON o.`id` = d.`order_id`
         LEFT JOIN `refills` r ON r.`id` = d.`refill_id`
         LEFT JOIN `pickups` p ON p.`id` = d.`pickup_id`
         LEFT JOIN `customers` cu ON cu.`id` = COALESCE(o.`customer_id`, r.`customer_id`, p.`customer_id`)
         LEFT JOIN `users` u ON u.`id` = cu.`user_id`
         WHERE d.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function del_kind($d) {
    if (!empty($d['order_id'])) {
        return ['order', $d['order_number'], 'Order ' . $d['order_number']];
    }
    if (!empty($d['refill_id'])) {
        return ['refill', $d['refill_number'], 'Refill ' . $d['refill_number']];
    }
    return ['pickup', $d['pickup_number'], 'Pickup ' . $d['pickup_number'] . ' (' . $d['pickup_type'] . ')'];
}

function del_list($status = '', $driver_id = 0, $limit = 100) {
    $sql = 'SELECT d.*, dr.`driver_code`, z.`name` AS zone_name,
                   o.`order_number`, r.`refill_number`, p.`pickup_number`, u.`name` AS customer_name
            FROM `deliveries` d
            LEFT JOIN `drivers` dr ON dr.`id` = d.`driver_id`
            LEFT JOIN `delivery_zones` z ON z.`id` = d.`zone_id`
            LEFT JOIN `orders` o ON o.`id` = d.`order_id`
            LEFT JOIN `refills` r ON r.`id` = d.`refill_id`
            LEFT JOIN `pickups` p ON p.`id` = d.`pickup_id`
            LEFT JOIN `customers` cu ON cu.`id` = COALESCE(o.`customer_id`, r.`customer_id`, p.`customer_id`)
            LEFT JOIN `users` u ON u.`id` = cu.`user_id` WHERE 1 = 1';
    $args = [];
    if (array_key_exists($status, del_flow())) {
        $sql .= ' AND d.`status` = ?';
        $args[] = $status;
    }
    if ($driver_id > 0) {
        $sql .= ' AND d.`driver_id` = ?';
        $args[] = (int) $driver_id;
    }
    $sql .= ' ORDER BY d.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function del_notify($d, $subject, $body) {
    if (empty($d['customer_id']) || empty($d['customer_email'])) {
        return;
    }
    try {
        notify_emit((int) $d['customer_id'], 'delivery_update',
            (string) $d['customer_email'], $subject, $body, 'email');
    } catch (Throwable $e) {
        error_log('del_notify: ' . $e->getMessage());
    }
}

function del_order_bump($pdo, $order_id, $from, $to, $actor_uid, $note) {
    $stmt = $pdo->prepare('SELECT `status` FROM `orders` WHERE `id` = ? FOR UPDATE');
    $stmt->execute([(int) $order_id]);
    if ($stmt->fetchColumn() !== $from) {
        return;
    }
    $pdo->prepare('UPDATE `orders` SET `status` = ? WHERE `id` = ?')->execute([$to, (int) $order_id]);
    $pdo->prepare(
        'INSERT INTO `order_status_history` (`order_id`, `from_status`, `to_status`, `changed_by`, `note`)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([(int) $order_id, $from, $to, $actor_uid ?: null, $note]);
}

/** Assign (or reassign) a driver; mirrors onto linked refill/pickup legs. */
function del_assign($id, $driver_id, $actor_id) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `deliveries` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $d = $stmt->fetch();
        if (!$d) {
            $pdo->rollBack();
            return [false, 'Delivery not found.'];
        }
        if (!in_array($d['status'], ['pending', 'assigned'], true)) {
            $pdo->rollBack();
            return [false, 'Only pending or assigned deliveries can be (re)assigned.'];
        }
        $stmt = $pdo->prepare("SELECT d.*, u.`name` FROM `drivers` d JOIN `users` u ON u.`id` = d.`user_id` WHERE d.`id` = ? AND d.`status` = 'active'");
        $stmt->execute([(int) $driver_id]);
        $dr = $stmt->fetch();
        if (!$dr) {
            $pdo->rollBack();
            return [false, 'Choose an active driver.'];
        }
        $pdo->prepare("UPDATE `deliveries` SET `driver_id` = ?, `status` = 'assigned' WHERE `id` = ?")
            ->execute([(int) $driver_id, (int) $id]);
        if ($d['refill_id']) {
            $pdo->prepare('UPDATE `refills` SET `assigned_driver_id` = ? WHERE `id` = ?')
                ->execute([(int) $driver_id, (int) $d['refill_id']]);
        }
        if ($d['pickup_id']) {
            $pdo->prepare('UPDATE `pickups` SET `driver_id` = ? WHERE `id` = ?')
                ->execute([(int) $driver_id, (int) $d['pickup_id']]);
        }
        del_audit('delivery.assign', $actor_id, (int) $id,
            ['driver_id' => $d['driver_id'] ? (int) $d['driver_id'] : null],
            ['driver_id' => (int) $driver_id]);
        $pdo->commit();
        $full = del_get((int) $id);
        if ($full) {
            [$kind, $ref, $label] = del_kind($full);
            del_notify($full, 'Driver assigned for ' . $label,
                $dr['name'] . ' (' . $dr['driver_code'] . ') is on the way for ' . $label . '.');
        }
        return [true, $d['delivery_number'] . ' assigned to ' . $dr['name'] . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('del_assign: ' . $e->getMessage());
        return [false, 'Could not assign the delivery. Please try again.'];
    }
}

/**
 * Status transition. $actor = ['user_id'=>int,'driver_id'=>int|null,'is_staff'=>bool].
 * Drivers may only move their own deliveries along the working path.
 */
function del_set_status($id, $to, $actor, $opts = []) {
    $flow = del_flow();
    if (!array_key_exists($to, $flow)) {
        return [false, 'Unknown status.'];
    }
    $is_staff = !empty($actor['is_staff']);
    $driver_id = (int) ($actor['driver_id'] ?? 0);
    if (!$is_staff && $driver_id <= 0) {
        return [false, 'Driver profile not found.'];
    }
    if (!$is_staff && !in_array($to, ['out_for_delivery', 'delivered', 'failed'], true)) {
        return [false, 'Drivers cannot set that status.'];
    }
    if ($to === 'delivered') {
        $opts['receiver'] = trim((string) ($opts['receiver'] ?? ''));
        $opts['proof_note'] = trim((string) ($opts['proof_note'] ?? ''));
        if ($opts['receiver'] === '' || $opts['proof_note'] === '') {
            return [false, 'Receiver name and a handover note are required for proof of delivery.'];
        }
    }
    if ($to === 'failed') {
        $opts['failed_reason'] = trim((string) ($opts['failed_reason'] ?? ''));
        if ($opts['failed_reason'] === '') {
            return [false, 'A reason is required for a failed delivery.'];
        }
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `deliveries` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $d = $stmt->fetch();
        if (!$d) {
            $pdo->rollBack();
            return [false, 'Delivery not found.'];
        }
        if (!$is_staff && (int) $d['driver_id'] !== $driver_id) {
            $pdo->rollBack();
            return [false, 'That delivery is not assigned to you.'];
        }
        if (!in_array($to, $flow[$d['status']], true)) {
            $pdo->rollBack();
            return [false, 'Cannot move a delivery from ' . $d['status'] . ' to ' . $to . '.'];
        }
        $extra = '';
        $args = [$to, (int) $id];
        if ($to === 'delivered') {
            $proof = 'Receiver: ' . mb_substr($opts['receiver'], 0, 120)
                . ' | ' . mb_substr($opts['proof_note'], 0, 350);
            $extra = ', `proof_note` = ?, `failed_reason` = NULL';
            $args = [$to, $proof, (int) $id];
        } elseif ($to === 'failed') {
            $extra = ', `failed_reason` = ?';
            $args = [$to, mb_substr($opts['failed_reason'], 0, 255), (int) $id];
        } elseif ($to === 'pending') {
            // Requeue to the pool.
            $extra = ', `driver_id` = NULL';
        }
        $pdo->prepare("UPDATE `deliveries` SET `status` = ?$extra WHERE `id` = ?")->execute($args);
        if ($d['order_id'] && $to === 'out_for_delivery') {
            del_order_bump($pdo, (int) $d['order_id'], 'preparing', 'out_for_delivery',
                (int) $actor['user_id'], 'Driver en route (' . $d['delivery_number'] . ')');
        }
        if ($d['order_id'] && $to === 'delivered') {
            del_order_bump($pdo, (int) $d['order_id'], 'out_for_delivery', 'delivered',
                (int) $actor['user_id'], 'Delivered (' . $d['delivery_number'] . ')');
        }
        del_audit('delivery.status', (int) $actor['user_id'], (int) $id,
            ['status' => $d['status']], ['status' => $to]);
        $pdo->commit();
        $full = del_get((int) $id);
        if ($full) {
            [$kind, $ref, $label] = del_kind($full);
            $msgs = [
                'out_for_delivery' => 'Your ' . $label . ' is out for delivery.',
                'delivered' => 'Your ' . $label . ' was delivered. Thank you!',
                'failed' => 'Delivery for ' . $label . ' could not be completed: ' . ($opts['failed_reason'] ?? ''),
                'cancelled' => 'The delivery for ' . $label . ' was cancelled.',
            ];
            if (isset($msgs[$to])) {
                del_notify($full, $label . ': ' . str_replace('_', ' ', $to), $msgs[$to]);
            }
        }
        return [true, $d['delivery_number'] . ' is now ' . str_replace('_', ' ', $to) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('del_set_status: ' . $e->getMessage());
        return [false, 'Could not update the delivery. Please try again.'];
    }
}

/** Driver note on own delivery (DL-10). */
function del_driver_note($id, $driver_id, $note) {
    $note = mb_substr(trim((string) $note), 0, 500);
    if ($note === '') {
        return [false, 'Note cannot be empty.'];
    }
    $stmt = db()->prepare('SELECT `driver_id`, `driver_note` FROM `deliveries` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $d = $stmt->fetch();
    if (!$d) {
        return [false, 'Delivery not found.'];
    }
    if ((int) $d['driver_id'] !== (int) $driver_id) {
        return [false, 'That delivery is not assigned to you.'];
    }
    $line = date('Y-m-d H:i') . ' — ' . $note;
    $merged = trim((string) ($d['driver_note'] ?? ''));
    $merged = $merged === '' ? $line : mb_substr($merged . "\n" . $line, -500);
    db()->prepare('UPDATE `deliveries` SET `driver_note` = ? WHERE `id` = ?')->execute([$merged, (int) $id]);
    return [true, 'Note saved.'];
}

/** Staff edit of customer instructions (DL-11). */
function del_instructions($id, $text, $actor_id) {
    $text = mb_substr(trim((string) $text), 0, 500);
    $stmt = db()->prepare('SELECT 1 FROM `deliveries` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->fetchColumn()) {
        return [false, 'Delivery not found.'];
    }
    db()->prepare('UPDATE `deliveries` SET `customer_instructions` = ? WHERE `id` = ?')
        ->execute([$text ?: null, (int) $id]);
    del_audit('delivery.instructions', $actor_id, (int) $id, null, ['text' => $text]);
    return [true, 'Instructions saved.'];
}

/** Optional POD photo upload (DL-14). JPG/PNG ≤ 2 MB into uploads/pod/. */
function del_pod_photo($id, $driver_id, $file) {
    $stmt = db()->prepare('SELECT `driver_id`, `status`, `delivery_number` FROM `deliveries` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $d = $stmt->fetch();
    if (!$d) {
        return [false, 'Delivery not found.'];
    }
    if ((int) $d['driver_id'] !== (int) $driver_id) {
        return [false, 'That delivery is not assigned to you.'];
    }
    if (!in_array($d['status'], ['out_for_delivery', 'delivered'], true)) {
        return [false, 'Photos can only be attached while out for delivery or after delivery.'];
    }
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Choose a photo to upload.'];
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        return [false, 'Photo must be 2 MB or smaller.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return [false, 'Only JPG or PNG photos are accepted.'];
    }
    $dir = BASE_PATH . '/uploads/pod';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [false, 'Could not store the photo.'];
    }
    $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
    $name = 'POD-' . (int) $id . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [false, 'Could not store the photo.'];
    }
    db()->prepare('UPDATE `deliveries` SET `proof_image` = ? WHERE `id` = ?')
        ->execute(['pod/' . $name, (int) $id]);
    return [true, 'Photo attached to ' . $d['delivery_number'] . '.'];
}

/** Cash collection record (DL-15). Reconciliation lands in Phase 17. */
function del_collect_cash($id, $driver_id, $naira, $notes, $actor_id) {
    $minor = (int) round((float) $naira * 100);
    if ($minor < 1 || $minor > 5000000000) {
        return [false, 'Amount looks invalid.'];
    }
    $notes = mb_substr(trim((string) $notes), 0, 255);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `deliveries` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $d = $stmt->fetch();
        if (!$d) {
            $pdo->rollBack();
            return [false, 'Delivery not found.'];
        }
        if ((int) $d['driver_id'] !== (int) $driver_id) {
            $pdo->rollBack();
            return [false, 'That delivery is not assigned to you.'];
        }
        if (!in_array($d['status'], ['out_for_delivery', 'delivered'], true)) {
            $pdo->rollBack();
            return [false, 'Cash can only be recorded while out for delivery or after delivery.'];
        }
        $pdo->prepare(
            'INSERT INTO `driver_cash_collections` (`delivery_id`, `driver_id`, `amount_minor`, `notes`)
             VALUES (?, ?, ?, ?)'
        )->execute([(int) $id, (int) $driver_id, $minor, $notes ?: null]);
        $pdo->prepare('UPDATE `deliveries` SET `cash_collected_minor` = `cash_collected_minor` + ? WHERE `id` = ?')
            ->execute([$minor, (int) $id]);
        del_audit('delivery.cash', $actor_id, (int) $id, null, ['amount_minor' => $minor]);
        $pdo->commit();
        if ($d['order_id'] && function_exists('pay_cod_autoverify')) {
            pay_cod_autoverify((int) $d['order_id']);
        }
        return [true, '₦' . number_format($minor / 100, 2) . ' recorded for ' . $d['delivery_number'] . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('del_collect_cash: ' . $e->getMessage());
        return [false, 'Could not record the cash. Please try again.'];
    }
}

function del_cash_list($delivery_id = 0, $driver_id = 0, $limit = 100) {
    $sql = 'SELECT cc.*, d.`delivery_number`, dr.`driver_code`
            FROM `driver_cash_collections` cc
            JOIN `deliveries` d ON d.`id` = cc.`delivery_id`
            JOIN `drivers` dr ON dr.`id` = cc.`driver_id` WHERE 1 = 1';
    $args = [];
    if ($delivery_id > 0) {
        $sql .= ' AND cc.`delivery_id` = ?';
        $args[] = (int) $delivery_id;
    }
    if ($driver_id > 0) {
        $sql .= ' AND cc.`driver_id` = ?';
        $args[] = (int) $driver_id;
    }
    $sql .= ' ORDER BY cc.`id` DESC LIMIT ' . max(1, min(300, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function del_driver_profile($user_id) {
    $stmt = db()->prepare('SELECT * FROM `drivers` WHERE `user_id` = ?');
    $stmt->execute([(int) $user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function del_availability($driver_id, $value, $actor_id) {
    if (!in_array($value, ['available', 'busy', 'off_duty'], true)) {
        return [false, 'Unknown availability.'];
    }
    db()->prepare('UPDATE `drivers` SET `availability` = ? WHERE `id` = ?')
        ->execute([$value, (int) $driver_id]);
    del_audit('delivery.availability', $actor_id, (int) $driver_id, null, ['availability' => $value]);
    return [true, 'You are now ' . str_replace('_', ' ', $value) . '.'];
}

/** Driver performance + logistics reports (DL-16, DL-17). */
function del_performance() {
    return db()->query(
        "SELECT dr.`id`, dr.`driver_code`, u.`name`,
                SUM(d.`status` = 'assigned') AS assigned,
                SUM(d.`status` = 'out_for_delivery') AS en_route,
                SUM(d.`status` = 'delivered') AS delivered,
                SUM(d.`status` = 'failed') AS failed,
                SUM(d.`status` = 'cancelled') AS cancelled,
                COALESCE(SUM(d.`cash_collected_minor`), 0) AS cash_minor
         FROM `drivers` dr JOIN `users` u ON u.`id` = dr.`user_id`
         LEFT JOIN `deliveries` d ON d.`driver_id` = dr.`id`
         GROUP BY dr.`id`, dr.`driver_code`, u.`name` ORDER BY u.`name`"
    )->fetchAll();
}

function del_reports() {
    $pdo = db();
    return [
        'by_status' => $pdo->query(
            'SELECT `status`, COUNT(*) AS n FROM `deliveries` GROUP BY `status`'
        )->fetchAll(),
        'by_zone' => $pdo->query(
            'SELECT COALESCE(z.`name`, \'—\') AS zone, COUNT(*) AS n
             FROM `deliveries` d LEFT JOIN `delivery_zones` z ON z.`id` = d.`zone_id`
             GROUP BY zone ORDER BY n DESC'
        )->fetchAll(),
        'cash' => $pdo->query(
            'SELECT COALESCE(SUM(`cash_expected_minor`), 0) AS expected,
                    COALESCE(SUM(`cash_collected_minor`), 0) AS collected FROM `deliveries`'
        )->fetch(),
    ];
}
