<?php
/**
 * Oyejo Gas - inventory, cylinder tracking, suppliers and purchases.
 * Phase 14. All mutations run server-side inside transactions and are audited.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function inv_audit($action, $user_id, $entity_id, $old, $new) {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'inventory', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Cylinder counts grouped by status (IV-01…IV-04). */
function inv_cylinder_counts() {
    $rows = db()->query('SELECT `status`, COUNT(*) AS n FROM `cylinders` GROUP BY `status`')->fetchAll();
    $out = ['full' => 0, 'empty' => 0, 'awaiting_refill' => 0, 'damaged' => 0, 'retired' => 0, 'in_transit' => 0];
    foreach ($rows as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/** Tracked products with stock + low-stock flag (IV-06, IV-11). */
function inv_products($type = '') {
    $sql = 'SELECT `id`, `sku`, `name`, `type`, `price_minor`, `stock_qty`, `low_stock_at`, `track_inventory`, `is_active`
            FROM `products`';
    $args = [];
    if ($type !== '') {
        $sql .= ' WHERE `type` = ?';
        $args[] = $type;
    }
    $sql .= ' ORDER BY `name`';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function inv_low_stock() {
    $stmt = db()->query(
        "SELECT `id`, `sku`, `name`, `stock_qty`, `low_stock_at` FROM `products`
         WHERE `track_inventory` = 1 AND `is_active` = 1 AND `stock_qty` <= `low_stock_at`
         ORDER BY `stock_qty`, `name`"
    );
    return $stmt->fetchAll();
}

/** Serial search (unique serials enforced by uq_cylinders_serial) — IV-05. */
function inv_search_cylinders($q, $status = '', $size_id = 0, $limit = 50) {
    $sql = 'SELECT c.`id`, c.`serial`, c.`ownership`, c.`status`, c.`holder_customer_id`, c.`location_note`,
                   s.`name` AS size_name, s.`code` AS size_code
            FROM `cylinders` c JOIN `cylinder_sizes` s ON s.`id` = c.`size_id` WHERE 1 = 1';
    $args = [];
    if ($q !== '') {
        $sql .= ' AND c.`serial` LIKE ?';
        $args[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    if ($status !== '') {
        $sql .= ' AND c.`status` = ?';
        $args[] = $status;
    }
    if ($size_id > 0) {
        $sql .= ' AND c.`size_id` = ?';
        $args[] = $size_id;
    }
    $sql .= ' ORDER BY c.`serial` LIMIT ' . max(1, min(200, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function inv_cylinder_get($id) {
    $stmt = db()->prepare(
        'SELECT c.*, s.`name` AS size_name FROM `cylinders` c
         JOIN `cylinder_sizes` s ON s.`id` = c.`size_id` WHERE c.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function inv_cylinder_statuses() {
    return ['full', 'empty', 'awaiting_refill', 'damaged', 'retired', 'in_transit'];
}

/** Staff status change for tracked cylinders (damage/retire/transit). */
function inv_cylinder_set_status($id, $status, $note, $actor_id) {
    $statuses = inv_cylinder_statuses();
    if (!in_array($status, $statuses, true)) {
        return [false, 'Unknown cylinder status.'];
    }
    $note = trim((string) $note);
    if (in_array($status, ['damaged', 'retired'], true) && $note === '') {
        return [false, 'A note is required when marking a cylinder damaged or retired.'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `cylinders` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $cyl = $stmt->fetch();
        if (!$cyl) {
            $pdo->rollBack();
            return [false, 'Cylinder not found.'];
        }
        $stmt = $pdo->prepare('UPDATE `cylinders` SET `status` = ?, `location_note` = ? WHERE `id` = ?');
        $stmt->execute([$status, $note === '' ? $cyl['location_note'] : mb_substr($note, 0, 255), (int) $id]);
        inv_audit('inventory.cylinder_status', $actor_id, (int) $id,
            ['status' => $cyl['status']], ['status' => $status, 'note' => $note]);
        $pdo->commit();
        return [true, 'Cylinder ' . $cyl['serial'] . ' is now ' . str_replace('_', ' ', $status) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('inv_cylinder_set_status: ' . $e->getMessage());
        return [false, 'Could not update the cylinder. Please try again.'];
    }
}

function inv_product_get($id) {
    $stmt = db()->prepare('SELECT * FROM `products` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function inv_log_movement($pdo, $product_id, $movement, $qty, $ref_type, $ref_id, $reason, $actor_id) {
    $stmt = $pdo->prepare(
        'INSERT INTO `inventory_movements` (`product_id`, `cylinder_id`, `movement`, `qty`, `ref_type`, `ref_id`, `reason`, `created_by`)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int) $product_id, $movement, (int) $qty, $ref_type, $ref_id ? (int) $ref_id : null,
        mb_substr($reason, 0, 255), $actor_id ?: null]);
}

/** Absolute stock adjustment with mandatory reason (IV-07). */
function inv_adjust($product_id, $new_qty, $reason, $actor_id) {
    $new_qty = (int) $new_qty;
    $reason = trim((string) $reason);
    if ($new_qty < 0 || $new_qty > 1000000) {
        return [false, 'Stock level must be between 0 and 1,000,000.'];
    }
    if (mb_strlen($reason) < 3) {
        return [false, 'A reason is required for every stock adjustment.'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `products` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $product_id]);
        $p = $stmt->fetch();
        if (!$p) {
            $pdo->rollBack();
            return [false, 'Product not found.'];
        }
        if (!(int) $p['track_inventory']) {
            $pdo->rollBack();
            return [false, 'That product does not track inventory.'];
        }
        $stmt = $pdo->prepare('UPDATE `products` SET `stock_qty` = ? WHERE `id` = ?');
        $stmt->execute([$new_qty, (int) $product_id]);
        inv_log_movement($pdo, (int) $product_id, 'adjustment', abs($new_qty - (int) $p['stock_qty']),
            'manual', null, 'Adjustment ' . (int) $p['stock_qty'] . ' → ' . $new_qty . ': ' . $reason, $actor_id);
        inv_audit('inventory.adjust', $actor_id, (int) $product_id,
            ['stock' => (int) $p['stock_qty']], ['stock' => $new_qty, 'reason' => $reason]);
        $pdo->commit();
        return [true, $p['name'] . ' stock set to ' . number_format($new_qty) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('inv_adjust: ' . $e->getMessage());
        return [false, 'Could not adjust stock. Please try again.'];
    }
}

/** Relative stock movement: addition / deduction / transfer_in / transfer_out (IV-08…IV-10). */
function inv_move($product_id, $movement, $qty, $reason, $actor_id) {
    $allowed = ['addition', 'deduction', 'transfer_in', 'transfer_out'];
    if (!in_array($movement, $allowed, true)) {
        return [false, 'Unknown movement type.'];
    }
    $qty = (int) $qty;
    $reason = trim((string) $reason);
    if ($qty < 1 || $qty > 100000) {
        return [false, 'Quantity must be between 1 and 100,000.'];
    }
    if (mb_strlen($reason) < 3) {
        return [false, 'A reason is required for every stock movement.'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `products` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $product_id]);
        $p = $stmt->fetch();
        if (!$p) {
            $pdo->rollBack();
            return [false, 'Product not found.'];
        }
        if (!(int) $p['track_inventory']) {
            $pdo->rollBack();
            return [false, 'That product does not track inventory.'];
        }
        $delta = in_array($movement, ['addition', 'transfer_in'], true) ? $qty : -$qty;
        $new = (int) $p['stock_qty'] + $delta;
        if ($new < 0) {
            $pdo->rollBack();
            return [false, 'Not enough stock — only ' . (int) $p['stock_qty'] . ' available.'];
        }
        $stmt = $pdo->prepare('UPDATE `products` SET `stock_qty` = ? WHERE `id` = ?');
        $stmt->execute([$new, (int) $product_id]);
        inv_log_movement($pdo, (int) $product_id, $movement, $qty, 'manual', null, $reason, $actor_id);
        inv_audit('inventory.move', $actor_id, (int) $product_id,
            ['stock' => (int) $p['stock_qty'], 'movement' => $movement],
            ['stock' => $new, 'qty' => $qty, 'reason' => $reason]);
        $pdo->commit();
        return [true, $p['name'] . ' stock is now ' . number_format($new) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('inv_move: ' . $e->getMessage());
        return [false, 'Could not record the movement. Please try again.'];
    }
}

/** Movement history with product + actor names (IV-12). */
function inv_movements($product_id = 0, $limit = 100) {
    $sql = 'SELECT m.*, p.`name` AS product_name, p.`sku`, u.`name` AS actor_name
            FROM `inventory_movements` m
            LEFT JOIN `products` p ON p.`id` = m.`product_id`
            LEFT JOIN `users` u ON u.`id` = m.`created_by`';
    $args = [];
    if ($product_id > 0) {
        $sql .= ' WHERE m.`product_id` = ?';
        $args[] = (int) $product_id;
    }
    $sql .= ' ORDER BY m.`id` DESC LIMIT ' . max(1, min(500, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/* ---------------- suppliers (IV-13) ---------------- */

function inv_suppliers() {
    return db()->query(
        'SELECT s.*, (SELECT COUNT(*) FROM `purchases` p WHERE p.`supplier_id` = s.`id`) AS purchase_count
         FROM `suppliers` s ORDER BY s.`name`'
    )->fetchAll();
}

function inv_supplier_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 190) {
        return [false, 'Supplier name is required (max 190 characters).'];
    }
    $email = trim((string) ($data['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'That email address looks invalid.'];
    }
    $fields = [
        'name' => mb_substr($name, 0, 190),
        'contact_person' => mb_substr(trim((string) ($data['contact_person'] ?? '')), 0, 150) ?: null,
        'phone' => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 30) ?: null,
        'email' => $email === '' ? null : mb_substr($email, 0, 190),
        'address' => mb_substr(trim((string) ($data['address'] ?? '')), 0, 255) ?: null,
        'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
    ];
    $pdo = db();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM `suppliers` WHERE `id` = ?');
            $stmt->execute([(int) $id]);
            $old = $stmt->fetch();
            if (!$old) {
                return [false, 'Supplier not found.'];
            }
            $stmt = $pdo->prepare(
                'UPDATE `suppliers` SET `name` = ?, `contact_person` = ?, `phone` = ?, `email` = ?, `address` = ?, `notes` = ?
                 WHERE `id` = ?'
            );
            $stmt->execute(array_merge(array_values($fields), [(int) $id]));
            inv_audit('inventory.supplier_update', $actor_id, (int) $id, ['name' => $old['name']], $fields);
            return [true, 'Supplier updated.'];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`, `notes`)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array_values($fields));
        $sid = (int) $pdo->lastInsertId();
        inv_audit('inventory.supplier_create', $actor_id, $sid, null, $fields);
        return [true, 'Supplier added.'];
    } catch (Throwable $e) {
        error_log('inv_supplier_save: ' . $e->getMessage());
        return [false, 'Could not save the supplier. Please try again.'];
    }
}

function inv_supplier_delete($id, $actor_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `suppliers` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $old = $stmt->fetch();
    if (!$old) {
        return [false, 'Supplier not found.'];
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `purchases` WHERE `supplier_id` = ?');
    $stmt->execute([(int) $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return [false, 'That supplier has purchase records and cannot be deleted.'];
    }
    $stmt = $pdo->prepare('DELETE FROM `suppliers` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    inv_audit('inventory.supplier_delete', $actor_id, (int) $id, ['name' => $old['name']], null);
    return [true, 'Supplier deleted.'];
}

/* ---------------- purchases (IV-14) ---------------- */

function inv_purchase_number($pdo) {
    for ($i = 0; $i < 5; $i++) {
        $n = 'PUR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt = $pdo->prepare('SELECT 1 FROM `purchases` WHERE `purchase_number` = ?');
        $stmt->execute([$n]);
        if (!$stmt->fetchColumn()) {
            return $n;
        }
    }
    return 'PUR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
}

function inv_purchase_create($supplier_id, $items, $notes, $actor_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `suppliers` WHERE `id` = ?');
    $stmt->execute([(int) $supplier_id]);
    if (!$stmt->fetch()) {
        return [false, 'Choose a valid supplier.'];
    }
    $clean = [];
    $total = 0;
    foreach ((array) $items as $it) {
        $desc = trim((string) ($it['description'] ?? ''));
        $qty = (int) ($it['qty'] ?? 0);
        $cost = (int) round((float) ($it['unit_cost'] ?? 0) * 100);
        $pid = (int) ($it['product_id'] ?? 0);
        if ($desc === '' && $qty <= 0) {
            continue;
        }
        if ($desc === '' || mb_strlen($desc) > 255) {
            return [false, 'Every line needs a description (max 255 characters).'];
        }
        if ($qty < 1 || $qty > 100000) {
            return [false, 'Line quantities must be between 1 and 100,000.'];
        }
        if ($cost < 0 || $cost > 100000000) {
            return [false, 'Unit cost looks invalid.'];
        }
        if ($pid > 0) {
            $chk = $pdo->prepare('SELECT `track_inventory` FROM `products` WHERE `id` = ?');
            $chk->execute([$pid]);
            if ($chk->fetchColumn() === false) {
                return [false, 'One of the linked products does not exist.'];
            }
        }
        $clean[] = ['product_id' => $pid ?: null, 'description' => $desc, 'qty' => $qty, 'unit_cost_minor' => $cost];
        $total += $qty * $cost;
    }
    if (!$clean) {
        return [false, 'Add at least one purchase line.'];
    }
    try {
        $pdo->beginTransaction();
        $number = inv_purchase_number($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO `purchases` (`purchase_number`, `supplier_id`, `total_minor`, `status`, `notes`, `created_by`)
             VALUES (?, ?, ?, \'ordered\', ?, ?)'
        );
        $stmt->execute([$number, (int) $supplier_id, $total,
            trim((string) $notes) === '' ? null : trim((string) $notes), $actor_id ?: null]);
        $pur_id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'INSERT INTO `purchase_items` (`purchase_id`, `product_id`, `description`, `qty`, `unit_cost_minor`)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($clean as $c) {
            $stmt->execute([$pur_id, $c['product_id'], $c['description'], $c['qty'], $c['unit_cost_minor']]);
        }
        inv_audit('inventory.purchase_create', $actor_id, $pur_id, null,
            ['number' => $number, 'supplier_id' => (int) $supplier_id, 'total_minor' => $total, 'lines' => count($clean)]);
        $pdo->commit();
        return [true, 'Purchase ' . $number . ' recorded (' . count($clean) . ' lines).'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('inv_purchase_create: ' . $e->getMessage());
        return [false, 'Could not save the purchase. Please try again.'];
    }
}

function inv_purchases($status = '') {
    $sql = 'SELECT p.*, s.`name` AS supplier_name FROM `purchases` p
            JOIN `suppliers` s ON s.`id` = p.`supplier_id`';
    $args = [];
    if (in_array($status, ['ordered', 'received', 'cancelled'], true)) {
        $sql .= ' WHERE p.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY p.`id` DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function inv_purchase_get($id) {
    $stmt = db()->prepare(
        'SELECT p.*, s.`name` AS supplier_name FROM `purchases` p
         JOIN `suppliers` s ON s.`id` = p.`supplier_id` WHERE p.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $pur = $stmt->fetch();
    if (!$pur) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT i.*, pr.`name` AS product_name FROM `purchase_items` i
         LEFT JOIN `products` pr ON pr.`id` = i.`product_id` WHERE i.`purchase_id` = ? ORDER BY i.`id`'
    );
    $stmt->execute([(int) $id]);
    $pur['items'] = $stmt->fetchAll();
    return $pur;
}

/** Receiving adds linked-product quantities back into stock (IV-10). */
function inv_purchase_receive($id, $actor_id) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `purchases` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $pur = $stmt->fetch();
        if (!$pur) {
            $pdo->rollBack();
            return [false, 'Purchase not found.'];
        }
        if ($pur['status'] !== 'ordered') {
            $pdo->rollBack();
            return [false, 'Only ordered purchases can be received.'];
        }
        $stmt = $pdo->prepare('SELECT * FROM `purchase_items` WHERE `purchase_id` = ?');
        $stmt->execute([(int) $id]);
        $linked = 0;
        foreach ($stmt->fetchAll() as $it) {
            if (!(int) $it['product_id']) {
                continue;
            }
            $lock = $pdo->prepare('SELECT `stock_qty`, `track_inventory` FROM `products` WHERE `id` = ? FOR UPDATE');
            $lock->execute([(int) $it['product_id']]);
            $p = $lock->fetch();
            if ($p && (int) $p['track_inventory']) {
                $up = $pdo->prepare('UPDATE `products` SET `stock_qty` = `stock_qty` + ? WHERE `id` = ?');
                $up->execute([(int) $it['qty'], (int) $it['product_id']]);
                inv_log_movement($pdo, (int) $it['product_id'], 'addition', (int) $it['qty'],
                    'purchase', (int) $id, 'Received ' . $pur['purchase_number'], $actor_id);
                $linked++;
            }
        }
        $stmt = $pdo->prepare("UPDATE `purchases` SET `status` = 'received', `received_at` = NOW() WHERE `id` = ?");
        $stmt->execute([(int) $id]);
        inv_audit('inventory.purchase_receive', $actor_id, (int) $id,
            ['status' => 'ordered'], ['status' => 'received', 'linked_lines' => $linked]);
        $pdo->commit();
        return [true, $pur['purchase_number'] . ' received — ' . $linked . ' line(s) added to stock.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('inv_purchase_receive: ' . $e->getMessage());
        return [false, 'Could not receive the purchase. Please try again.'];
    }
}

function inv_purchase_cancel($id, $actor_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM `purchases` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $pur = $stmt->fetch();
    if (!$pur) {
        return [false, 'Purchase not found.'];
    }
    if ($pur['status'] !== 'ordered') {
        return [false, 'Only ordered purchases can be cancelled.'];
    }
    $stmt = $pdo->prepare("UPDATE `purchases` SET `status` = 'cancelled' WHERE `id` = ?");
    $stmt->execute([(int) $id]);
    inv_audit('inventory.purchase_cancel', $actor_id, (int) $id, ['status' => 'ordered'], ['status' => 'cancelled']);
    return [true, $pur['purchase_number'] . ' cancelled.'];
}

/** Simple stock reports (IV-15): valuation + 30-day movement summary + purchase totals. */
function inv_reports() {
    $pdo = db();
    $valuation = (int) $pdo->query(
        'SELECT COALESCE(SUM(`stock_qty` * `price_minor`), 0) FROM `products` WHERE `track_inventory` = 1'
    )->fetchColumn();
    $movements = $pdo->query(
        "SELECT `movement`, COUNT(*) AS n, COALESCE(SUM(`qty`), 0) AS units FROM `inventory_movements`
         WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY `movement`"
    )->fetchAll();
    $purchases = $pdo->query(
        'SELECT `status`, COUNT(*) AS n, COALESCE(SUM(`total_minor`), 0) AS total
         FROM `purchases` GROUP BY `status`'
    )->fetchAll();
    return ['valuation_minor' => $valuation, 'movements' => $movements, 'purchases' => $purchases];
}
