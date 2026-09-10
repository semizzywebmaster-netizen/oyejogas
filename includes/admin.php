<?php
/**
 * Oyejo Gas - back-office management library (Phase 15).
 * Customers, staff, drivers, roles, products, orders, coupons,
 * settings, toggles and add-ons. All mutations audited.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(404);
    exit;
}

function adm_audit($action, $user_id, $entity_id, $old, $new) {
    $stmt = db()->prepare(
        'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id ?: null, $action, 'admin', $entity_id ?: null,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function adm_slug($text) {
    $s = strtolower(trim((string) $text));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s === '' ? 'item' : substr($s, 0, 100);
}

/* ---------------- customers (AD-01) ---------------- */

function adm_customers($q = '', $status = '', $limit = 50) {
    $sql = 'SELECT c.`id`, c.`customer_code`, c.`referral_code`, c.`notes`,
                   u.`id` AS user_id, u.`name`, u.`email`, u.`phone`, u.`status`, u.`created_at`,
                   (SELECT COUNT(*) FROM `orders` o WHERE o.`customer_id` = c.`id`) AS order_count
            FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if ($q !== '') {
        $sql .= ' AND (u.`name` LIKE ? OR u.`email` LIKE ? OR u.`phone` LIKE ? OR c.`customer_code` LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        array_push($args, $like, $like, $like, $like);
    }
    if (in_array($status, ['active', 'pending', 'suspended'], true)) {
        $sql .= ' AND u.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY c.`id` DESC LIMIT ' . max(1, min(200, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function adm_customer_get($id) {
    $stmt = db()->prepare(
        'SELECT c.*, u.`name`, u.`email`, u.`phone`, u.`status`, u.`email_verified_at`,
                u.`phone_verified_at`, u.`last_login_at`, u.`created_at`
         FROM `customers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $c = $stmt->fetch();
    if (!$c) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT `id`, `order_number`, `status`, `total_minor`, `payment_status`, `created_at`
         FROM `orders` WHERE `customer_id` = ? ORDER BY `id` DESC LIMIT 20'
    );
    $stmt->execute([(int) $id]);
    $c['orders'] = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT COUNT(*) FROM `customer_addresses` WHERE `customer_id` = ?');
    $stmt->execute([(int) $id]);
    $c['address_count'] = (int) $stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COALESCE(SUM(`total_minor`), 0) FROM `orders` WHERE `customer_id` = ? AND `payment_status` = \'paid\'');
    $stmt->execute([(int) $id]);
    $c['paid_total_minor'] = (int) $stmt->fetchColumn();
    return $c;
}

function adm_customer_notes($id, $notes, $actor_id) {
    $stmt = db()->prepare('SELECT `notes` FROM `customers` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $old = $stmt->fetchColumn();
    if ($old === false) {
        return [false, 'Customer not found.'];
    }
    $notes = mb_substr(trim((string) $notes), 0, 2000);
    db()->prepare('UPDATE `customers` SET `notes` = ? WHERE `id` = ?')->execute([$notes ?: null, (int) $id]);
    adm_audit('admin.customer_notes', $actor_id, (int) $id, ['notes' => $old], ['notes' => $notes]);
    return [true, 'Customer notes saved.'];
}

/** Shared user-status changer (staff + customers). Never suspend yourself. */
function adm_user_status($user_id, $status, $actor_id) {
    if (!in_array($status, ['active', 'pending', 'suspended'], true)) {
        return [false, 'Unknown status.'];
    }
    if ((int) $user_id === (int) $actor_id) {
        return [false, 'You cannot change your own account status.'];
    }
    $stmt = db()->prepare('SELECT `status`, `name` FROM `users` WHERE `id` = ?');
    $stmt->execute([(int) $user_id]);
    $u = $stmt->fetch();
    if (!$u) {
        return [false, 'User not found.'];
    }
    db()->prepare('UPDATE `users` SET `status` = ? WHERE `id` = ?')->execute([$status, (int) $user_id]);
    adm_audit('admin.user_status', $actor_id, (int) $user_id,
        ['status' => $u['status']], ['status' => $status]);
    return [true, $u['name'] . ' is now ' . $status . '.'];
}

/* ---------------- staff (AD-02, AD-04) ---------------- */

function adm_staff($q = '') {
    $sql = 'SELECT u.`id`, u.`name`, u.`email`, u.`phone`, u.`status`, u.`last_login_at`, u.`created_at`,
                   r.`name` AS role_name, r.`slug` AS role_slug
            FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id`
            WHERE r.`slug` <> \'customer\'';
    $args = [];
    if ($q !== '') {
        $sql .= ' AND (u.`name` LIKE ? OR u.`email` LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        array_push($args, $like, $like);
    }
    $sql .= ' ORDER BY u.`name` LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function adm_roles_simple() {
    return db()->query('SELECT `id`, `slug`, `name` FROM `roles` ORDER BY `name`')->fetchAll();
}

function adm_staff_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = mb_substr(trim((string) ($data['phone'] ?? '')), 0, 30);
    $role_id = (int) ($data['role_id'] ?? 0);
    $status = $data['status'] ?? 'active';
    if ($name === '' || mb_strlen($name) > 150) {
        return [false, 'Name is required (max 150 characters).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        return [false, 'A valid email address is required.'];
    }
    if (!in_array($status, ['active', 'pending', 'suspended'], true)) {
        return [false, 'Unknown status.'];
    }
    $stmt = db()->prepare('SELECT `id`, `slug`, `name` FROM `roles` WHERE `id` = ?');
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();
    if (!$role || $role['slug'] === 'customer') {
        return [false, 'Choose a valid staff role.'];
    }
    $pdo = db();
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        $old = $stmt->fetch();
        if (!$old) {
            return [false, 'Staff member not found.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `users` WHERE `email` = ? AND `id` <> ?');
        $stmt->execute([$email, (int) $id]);
        if ($stmt->fetchColumn()) {
            return [false, 'That email address is already in use.'];
        }
        // Nobody may escalate or lock their own account.
        if ((int) $id === (int) $actor_id) {
            $role_id = (int) $old['role_id'];
            $status = $old['status'];
        }
        $stmt = $pdo->prepare(
            'UPDATE `users` SET `name` = ?, `email` = ?, `phone` = ?, `role_id` = ?, `status` = ? WHERE `id` = ?'
        );
        $stmt->execute([$name, $email, $phone ?: null, $role_id, $status, (int) $id]);
        adm_audit('admin.staff_update', $actor_id, (int) $id,
            ['role_id' => (int) $old['role_id'], 'status' => $old['status']],
            ['role_id' => $role_id, 'status' => $status]);
        return [true, 'Staff record updated.'];
    }
    $pw1 = (string) ($data['password'] ?? '');
    $pw2 = (string) ($data['password_confirm'] ?? '');
    if (mb_strlen($pw1) < 8) {
        return [false, 'Temporary password must be at least 8 characters.'];
    }
    if ($pw1 !== $pw2) {
        return [false, 'Passwords do not match.'];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `users` WHERE `email` = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        return [false, 'That email address is already in use.'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO `users` (`role_id`, `name`, `email`, `phone`, `password_hash`, `status`, `email_verified_at`)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$role_id, $name, $email, $phone ?: null, password_hash($pw1, PASSWORD_DEFAULT), $status]);
    $nid = (int) $pdo->lastInsertId();
    adm_audit('admin.staff_create', $actor_id, $nid, null, ['email' => $email, 'role' => $role['slug']]);
    return [true, 'Staff account created for ' . $name . '.'];
}

function adm_password_reset($user_id, $pw1, $pw2, $actor_id) {
    if (mb_strlen($pw1) < 8) {
        return [false, 'New password must be at least 8 characters.'];
    }
    if ($pw1 !== $pw2) {
        return [false, 'Passwords do not match.'];
    }
    $stmt = db()->prepare('SELECT `name` FROM `users` WHERE `id` = ?');
    $stmt->execute([(int) $user_id]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        return [false, 'User not found.'];
    }
    db()->prepare('UPDATE `users` SET `password_hash` = ?, `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ?')
        ->execute([password_hash($pw1, PASSWORD_DEFAULT), (int) $user_id]);
    adm_audit('admin.password_reset', $actor_id, (int) $user_id, null, ['user' => $name]);
    return [true, 'Password reset for ' . $name . '.'];
}

/* ---------------- drivers (AD-03) ---------------- */

function adm_drivers() {
    return db()->query(
        'SELECT d.*, u.`name`, u.`email`, u.`phone`,
                (SELECT COUNT(*) FROM `deliveries` dv WHERE dv.`driver_id` = d.`id`) AS delivery_count
         FROM `drivers` d JOIN `users` u ON u.`id` = d.`user_id` ORDER BY d.`driver_code`'
    )->fetchAll();
}

function adm_driver_users() {
    $stmt = db()->query(
        "SELECT u.`id`, u.`name`, u.`email` FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id`
         WHERE r.`slug` = 'driver' AND u.`status` = 'active'
           AND NOT EXISTS (SELECT 1 FROM `drivers` d WHERE d.`user_id` = u.`id`) ORDER BY u.`name`"
    );
    return $stmt->fetchAll();
}

function adm_driver_save($id, $data, $actor_id) {
    $code = strtoupper(trim((string) ($data['driver_code'] ?? '')));
    $vehicle = mb_substr(trim((string) ($data['vehicle_info'] ?? '')), 0, 255);
    $license = mb_substr(trim((string) ($data['license_no'] ?? '')), 0, 100);
    $availability = $data['availability'] ?? 'off_duty';
    $status = $data['status'] ?? 'active';
    if (!preg_match('/^[A-Z0-9-]{2,20}$/', $code)) {
        return [false, 'Driver code must be 2–20 letters, digits or dashes.'];
    }
    if (!in_array($availability, ['available', 'busy', 'off_duty'], true)) {
        return [false, 'Unknown availability.'];
    }
    if (!in_array($status, ['active', 'suspended'], true)) {
        return [false, 'Unknown status.'];
    }
    $pdo = db();
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM `drivers` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        $old = $stmt->fetch();
        if (!$old) {
            return [false, 'Driver not found.'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `drivers` WHERE `driver_code` = ? AND `id` <> ?');
        $stmt->execute([$code, (int) $id]);
        if ($stmt->fetchColumn()) {
            return [false, 'That driver code is already in use.'];
        }
        $stmt = $pdo->prepare(
            'UPDATE `drivers` SET `driver_code` = ?, `vehicle_info` = ?, `license_no` = ?, `availability` = ?, `status` = ?
             WHERE `id` = ?'
        );
        $stmt->execute([$code, $vehicle ?: null, $license ?: null, $availability, $status, (int) $id]);
        adm_audit('admin.driver_update', $actor_id, (int) $id,
            ['availability' => $old['availability'], 'status' => $old['status']],
            ['code' => $code, 'availability' => $availability, 'status' => $status]);
        return [true, 'Driver ' . $code . ' updated.'];
    }
    $user_id = (int) ($data['user_id'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT u.`id` FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id`
         WHERE u.`id` = ? AND r.`slug` = 'driver'"
    );
    $stmt->execute([$user_id]);
    if (!$stmt->fetchColumn()) {
        return [false, 'Link an active user with the driver role (create one under Staff first).'];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `drivers` WHERE `user_id` = ?');
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That user already has a driver profile.'];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `drivers` WHERE `driver_code` = ?');
    $stmt->execute([$code]);
    if ($stmt->fetchColumn()) {
        return [false, 'That driver code is already in use.'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO `drivers` (`user_id`, `driver_code`, `vehicle_info`, `license_no`, `availability`, `status`)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $code, $vehicle ?: null, $license ?: null, $availability, $status]);
    adm_audit('admin.driver_create', $actor_id, (int) $pdo->lastInsertId(), null, ['code' => $code]);
    return [true, 'Driver ' . $code . ' created.'];
}

/* ---------------- roles & permissions (AD-05, AD-06) ---------------- */

function adm_roles() {
    return db()->query(
        'SELECT r.*, (SELECT COUNT(*) FROM `users` u WHERE u.`role_id` = r.`id`) AS user_count,
                (SELECT COUNT(*) FROM `role_permissions` rp WHERE rp.`role_id` = r.`id`) AS perm_count
         FROM `roles` r ORDER BY r.`name`'
    )->fetchAll();
}

function adm_permissions_grouped() {
    $rows = db()->query('SELECT `id`, `slug`, `name`, `group_name` FROM `permissions` ORDER BY `group_name`, `name`')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['group_name']][] = $r;
    }
    return $out;
}

function adm_role_grants($role_id) {
    $stmt = db()->prepare('SELECT `permission_id` FROM `role_permissions` WHERE `role_id` = ?');
    $stmt->execute([(int) $role_id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function adm_role_save($id, $data, $perm_ids, $actor_id, $actor_role_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $slug = strtolower(trim((string) ($data['slug'] ?? '')));
    $desc = mb_substr(trim((string) ($data['description'] ?? '')), 0, 255);
    if ($name === '' || mb_strlen($name) > 100) {
        return [false, 'Role name is required (max 100 characters).'];
    }
    if (!preg_match('/^[a-z0-9_]{2,50}$/', $slug)) {
        return [false, 'Slug must be 2–50 lowercase letters, digits or underscores.'];
    }
    $valid = db()->query('SELECT `id` FROM `permissions`')->fetchAll(PDO::FETCH_COLUMN);
    $valid = array_map('intval', $valid);
    $grants = array_values(array_intersect(array_map('intval', (array) $perm_ids), $valid));
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM `roles` WHERE `id` = ?');
            $stmt->execute([(int) $id]);
            $old = $stmt->fetch();
            if (!$old) {
                $pdo->rollBack();
                return [false, 'Role not found.'];
            }
            if ((int) $old['is_system'] && $slug !== $old['slug']) {
                $pdo->rollBack();
                return [false, 'System role slugs cannot be renamed.'];
            }
            $stmt = $pdo->prepare('SELECT 1 FROM `roles` WHERE `slug` = ? AND `id` <> ?');
            $stmt->execute([$slug, (int) $id]);
            if ($stmt->fetchColumn()) {
                $pdo->rollBack();
                return [false, 'That slug is already in use.'];
            }
            // Lockout guard: your own role must keep roles.manage.
            if ((int) $id === (int) $actor_role_id) {
                $stmt = $pdo->prepare("SELECT `id` FROM `permissions` WHERE `slug` = 'roles.manage'");
                $stmt->execute();
                $rm = (int) $stmt->fetchColumn();
                if ($rm && !in_array($rm, $grants, true)) {
                    $pdo->rollBack();
                    return [false, 'Your own role must keep the roles.manage permission.'];
                }
            }
            $pdo->prepare('UPDATE `roles` SET `name` = ?, `slug` = ?, `description` = ? WHERE `id` = ?')
                ->execute([$name, $slug, $desc ?: null, (int) $id]);
            $pdo->prepare('DELETE FROM `role_permissions` WHERE `role_id` = ?')->execute([(int) $id]);
            $ins = $pdo->prepare('INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)');
            foreach ($grants as $g) {
                $ins->execute([(int) $id, $g]);
            }
            adm_audit('admin.role_update', $actor_id, (int) $id, ['slug' => $old['slug']], ['slug' => $slug, 'perms' => count($grants)]);
            $pdo->commit();
            return [true, 'Role ' . $name . ' saved (' . count($grants) . ' permissions).'];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM `roles` WHERE `slug` = ?');
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn()) {
            $pdo->rollBack();
            return [false, 'That slug is already in use.'];
        }
        $pdo->prepare('INSERT INTO `roles` (`slug`, `name`, `description`, `is_system`) VALUES (?, ?, ?, 0)')
            ->execute([$slug, $name, $desc ?: null]);
        $nid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)');
        foreach ($grants as $g) {
            $ins->execute([$nid, $g]);
        }
        adm_audit('admin.role_create', $actor_id, $nid, null, ['slug' => $slug]);
        $pdo->commit();
        return [true, 'Role ' . $name . ' created.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('adm_role_save: ' . $e->getMessage());
        return [false, 'Could not save the role. Please try again.'];
    }
}

function adm_role_delete($id, $actor_role_id) {
    $stmt = db()->prepare('SELECT * FROM `roles` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'Role not found.'];
    }
    if ((int) $r['is_system']) {
        return [false, 'System roles cannot be deleted.'];
    }
    if ((int) $id === (int) $actor_role_id) {
        return [false, 'You cannot delete your own role.'];
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM `users` WHERE `role_id` = ?');
    $stmt->execute([(int) $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return [false, 'That role still has users — reassign them first.'];
    }
    db()->prepare('DELETE FROM `role_permissions` WHERE `role_id` = ?')->execute([(int) $id]);
    db()->prepare('DELETE FROM `roles` WHERE `id` = ?')->execute([(int) $id]);
    adm_audit('admin.role_delete', null, (int) $id, ['slug' => $r['slug']], null);
    return [true, 'Role deleted.'];
}

/* ---------------- products & categories (AD-07, AD-08) ---------------- */

function adm_categories() {
    return db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM `products` p WHERE p.`category_id` = c.`id`) AS product_count
         FROM `categories` c ORDER BY c.`sort_order`, c.`name`'
    )->fetchAll();
}

function adm_category_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $slug = adm_slug($data['slug'] ?? $name);
    $desc = mb_substr(trim((string) ($data['description'] ?? '')), 0, 2000);
    $sort = max(0, min(9999, (int) ($data['sort_order'] ?? 0)));
    $active = isset($data['is_active']) ? 1 : 0;
    if ($name === '' || mb_strlen($name) > 150) {
        return [false, 'Category name is required (max 150 characters).'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM `categories` WHERE `slug` = ? AND `id` <> ?');
    $stmt->execute([$slug, (int) $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That category slug is already in use.'];
    }
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `categories` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Category not found.'];
        }
        $pdo->prepare('UPDATE `categories` SET `name` = ?, `slug` = ?, `description` = ?, `sort_order` = ?, `is_active` = ? WHERE `id` = ?')
            ->execute([$name, $slug, $desc ?: null, $sort, $active, (int) $id]);
        adm_audit('admin.category_update', $actor_id, (int) $id, null, ['slug' => $slug]);
        return [true, 'Category saved.'];
    }
    $pdo->prepare('INSERT INTO `categories` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?)')
        ->execute([$slug, $name, $desc ?: null, $sort, $active]);
    adm_audit('admin.category_create', $actor_id, (int) $pdo->lastInsertId(), null, ['slug' => $slug]);
    return [true, 'Category created.'];
}

function adm_category_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM `products` WHERE `category_id` = ?');
    $stmt->execute([(int) $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return [false, 'That category still has products — move them first.'];
    }
    $stmt = db()->prepare('DELETE FROM `categories` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->rowCount()) {
        return [false, 'Category not found.'];
    }
    adm_audit('admin.category_delete', $actor_id, (int) $id, null, null);
    return [true, 'Category deleted.'];
}

function adm_product_types() {
    return ['cylinder_new', 'refill', 'exchange', 'accessory', 'service'];
}

function adm_product_save($id, $data, $actor_id) {
    $name = trim((string) ($data['name'] ?? ''));
    $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
    $slug = adm_slug($data['slug'] ?? $name);
    $slug = substr($slug, 0, 150);
    $type = $data['type'] ?? 'accessory';
    $category_id = (int) ($data['category_id'] ?? 0);
    $size_id = (int) ($data['size_id'] ?? 0);
    $price = (int) round((float) ($data['price'] ?? 0) * 100);
    $promo = trim((string) ($data['promo_price'] ?? '')) === '' ? null : (int) round((float) $data['promo_price'] * 100);
    $stock = (int) ($data['stock_qty'] ?? 0);
    $low = (int) ($data['low_stock_at'] ?? 5);
    $desc = mb_substr(trim((string) ($data['description'] ?? '')), 0, 5000);
    if ($name === '' || mb_strlen($name) > 190) {
        return [false, 'Product name is required (max 190 characters).'];
    }
    if (!preg_match('/^[A-Z0-9-]{2,60}$/', $sku)) {
        return [false, 'SKU must be 2–60 letters, digits or dashes.'];
    }
    if (!in_array($type, adm_product_types(), true)) {
        return [false, 'Unknown product type.'];
    }
    if ($price < 0 || $price > 100000000) {
        return [false, 'Price looks invalid.'];
    }
    if ($promo !== null && ($promo < 0 || $promo >= $price)) {
        return [false, 'Promo price must be below the regular price.'];
    }
    if ($stock < 0 || $stock > 1000000 || $low < 0 || $low > 1000000) {
        return [false, 'Stock figures look invalid.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM `categories` WHERE `id` = ?');
    $stmt->execute([$category_id]);
    if (!$stmt->fetchColumn()) {
        return [false, 'Choose a valid category.'];
    }
    if ($size_id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `cylinder_sizes` WHERE `id` = ?');
        $stmt->execute([$size_id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Choose a valid cylinder size.'];
        }
    }
    $promo_start = trim((string) ($data['promo_starts_at'] ?? '')) ?: null;
    $promo_end = trim((string) ($data['promo_ends_at'] ?? '')) ?: null;
    foreach (['promo_starts_at' => $promo_start, 'promo_ends_at' => $promo_end] as $k => $v) {
        if ($v !== null && strtotime($v) === false) {
            return [false, 'Promo date looks invalid.'];
        }
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `products` WHERE `sku` = ? AND `id` <> ?');
    $stmt->execute([$sku, (int) $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That SKU is already in use.'];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `products` WHERE `slug` = ? AND `id` <> ?');
    $stmt->execute([$slug, (int) $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That product slug is already in use.'];
    }
    $fields = [$category_id, $size_id ?: null, $sku, $slug, $name, $desc ?: null, $type, $price,
        $promo, $promo_start, $promo_end, $stock, $low,
        isset($data['track_inventory']) ? 1 : 0, isset($data['is_active']) ? 1 : 0,
        isset($data['is_featured']) ? 1 : 0, max(0, min(9999, (int) ($data['sort_order'] ?? 0)))];
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `products` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Product not found.'];
        }
        $pdo->prepare(
            'UPDATE `products` SET `category_id` = ?, `size_id` = ?, `sku` = ?, `slug` = ?, `name` = ?, `description` = ?,
             `type` = ?, `price_minor` = ?, `promo_price_minor` = ?, `promo_starts_at` = ?, `promo_ends_at` = ?,
             `stock_qty` = ?, `low_stock_at` = ?, `track_inventory` = ?, `is_active` = ?, `is_featured` = ?, `sort_order` = ?
             WHERE `id` = ?'
        )->execute(array_merge($fields, [(int) $id]));
        adm_audit('admin.product_update', $actor_id, (int) $id, null, ['sku' => $sku]);
        return [true, 'Product saved.'];
    }
    $pdo->prepare(
        'INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`,
         `promo_price_minor`, `promo_starts_at`, `promo_ends_at`, `stock_qty`, `low_stock_at`, `track_inventory`,
         `is_active`, `is_featured`, `sort_order`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute($fields);
    adm_audit('admin.product_create', $actor_id, (int) $pdo->lastInsertId(), null, ['sku' => $sku]);
    return [true, 'Product created.'];
}

function adm_product_delete($id, $actor_id) {
    $pdo = db();
    foreach (['order_items' => 'product_id', 'purchase_items' => 'product_id'] as $tbl => $col) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $stmt->execute([(int) $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return [false, 'That product is referenced by ' . str_replace('_', ' ', $tbl) . ' and cannot be deleted.'];
        }
    }
    $stmt = $pdo->prepare('DELETE FROM `products` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->rowCount()) {
        return [false, 'Product not found.'];
    }
    adm_audit('admin.product_delete', $actor_id, (int) $id, null, null);
    return [true, 'Product deleted.'];
}

/* ---------------- orders (AD-09) + delivery visibility (AD-12) ---------------- */

function adm_order_flow() {
    return [
        'pending' => ['confirmed'],
        'confirmed' => ['preparing'],
        'preparing' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed'],
        'failed' => ['preparing'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];
}

function adm_orders($status = '', $q = '', $limit = 50) {
    $sql = 'SELECT o.`id`, o.`order_number`, o.`status`, o.`total_minor`, o.`payment_status`, o.`payment_method`,
                   o.`created_at`, u.`name` AS customer_name
            FROM `orders` o JOIN `customers` c ON c.`id` = o.`customer_id`
            JOIN `users` u ON u.`id` = c.`user_id` WHERE 1 = 1';
    $args = [];
    if (array_key_exists($status, adm_order_flow())) {
        $sql .= ' AND o.`status` = ?';
        $args[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (o.`order_number` LIKE ? OR u.`name` LIKE ? OR u.`email` LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY o.`id` DESC LIMIT ' . max(1, min(200, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function adm_order_get($id) {
    $stmt = db()->prepare(
        'SELECT o.*, u.`name` AS customer_name, u.`email` AS customer_email, c.`customer_code`
         FROM `orders` o JOIN `customers` c ON c.`id` = o.`customer_id`
         JOIN `users` u ON u.`id` = c.`user_id` WHERE o.`id` = ?'
    );
    $stmt->execute([(int) $id]);
    $o = $stmt->fetch();
    if (!$o) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM `order_items` WHERE `order_id` = ? ORDER BY `id`');
    $stmt->execute([(int) $id]);
    $o['items'] = $stmt->fetchAll();
    $stmt = db()->prepare(
        'SELECT h.*, u.`name` AS actor_name FROM `order_status_history` h
         LEFT JOIN `users` u ON u.`id` = h.`changed_by` WHERE h.`order_id` = ? ORDER BY h.`id`'
    );
    $stmt->execute([(int) $id]);
    $o['history'] = $stmt->fetchAll();
    $stmt = db()->prepare(
        'SELECT d.*, dr.`driver_code` FROM `deliveries` d
         LEFT JOIN `drivers` dr ON dr.`id` = d.`driver_id` WHERE d.`order_id` = ? ORDER BY d.`id` DESC LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    $o['delivery'] = $stmt->fetch() ?: null;
    return $o;
}

function adm_order_notify($order, $subject, $body) {
    try {
        notify_emit((int) $order['customer_id'], 'order_update',
            (string) $order['customer_email'], $subject, $body, 'email');
    } catch (Throwable $e) {
        error_log('adm_order_notify: ' . $e->getMessage());
    }
}

function adm_order_status($id, $to, $note, $actor_id) {
    $flow = adm_order_flow();
    if (!array_key_exists($to, $flow)) {
        return [false, 'Unknown status.'];
    }
    $note = mb_substr(trim((string) $note), 0, 255);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $o = $stmt->fetch();
        if (!$o) {
            $pdo->rollBack();
            return [false, 'Order not found.'];
        }
        if (!in_array($to, $flow[$o['status']], true)) {
            $pdo->rollBack();
            return [false, 'Cannot move an order from ' . $o['status'] . ' to ' . $to . '.'];
        }
        $pdo->prepare('UPDATE `orders` SET `status` = ? WHERE `id` = ?')->execute([$to, (int) $id]);
        $pdo->prepare(
            'INSERT INTO `order_status_history` (`order_id`, `from_status`, `to_status`, `changed_by`, `note`)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([(int) $id, $o['status'], $to, $actor_id ?: null, $note ?: null]);
        adm_audit('admin.order_status', $actor_id, (int) $id,
            ['status' => $o['status']], ['status' => $to, 'note' => $note]);
        $pdo->commit();
        $full = adm_order_get((int) $id);
        if ($full) {
            adm_order_notify($full, 'Order ' . $o['order_number'] . ': ' . $to,
                'Your order ' . $o['order_number'] . ' is now ' . str_replace('_', ' ', $to) . '.');
        }
        return [true, 'Order ' . $o['order_number'] . ' is now ' . $to . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('adm_order_status: ' . $e->getMessage());
        return [false, 'Could not update the order. Please try again.'];
    }
}

/** Staff cancellation mirrors the customer rules: pending/confirmed only + restock. */
function adm_order_cancel($id, $reason, $actor_id) {
    $reason = mb_substr(trim((string) $reason), 0, 255);
    if ($reason === '') {
        return [false, 'A cancellation reason is required.'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE `id` = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $o = $stmt->fetch();
        if (!$o) {
            $pdo->rollBack();
            return [false, 'Order not found.'];
        }
        if (!in_array($o['status'], ['pending', 'confirmed'], true)) {
            $pdo->rollBack();
            return [false, 'Only pending or confirmed orders can be cancelled.'];
        }
        $pdo->prepare("UPDATE `orders` SET `status` = 'cancelled', `cancelled_reason` = ?, `cancelled_at` = NOW() WHERE `id` = ?")
            ->execute([$reason, (int) $id]);
        $pdo->prepare(
            'INSERT INTO `order_status_history` (`order_id`, `from_status`, `to_status`, `changed_by`, `note`)
             VALUES (?, ?, \'cancelled\', ?, ?)'
        )->execute([(int) $id, $o['status'], $actor_id ?: null, $reason]);
        $stmt = $pdo->prepare(
            'SELECT oi.`product_id`, oi.`qty`, p.`track_inventory` FROM `order_items` oi
             JOIN `products` p ON p.`id` = oi.`product_id` WHERE oi.`order_id` = ?'
        );
        $stmt->execute([(int) $id]);
        foreach ($stmt->fetchAll() as $it) {
            if ((int) $it['track_inventory']) {
                $pdo->prepare('UPDATE `products` SET `stock_qty` = `stock_qty` + ? WHERE `id` = ?')
                    ->execute([(int) $it['qty'], (int) $it['product_id']]);
            }
        }
        adm_audit('admin.order_cancel', $actor_id, (int) $id, ['status' => $o['status']], ['reason' => $reason]);
        require_once BASE_PATH . '/includes/payments.php';
        pay_invoice_sync((int) $id);
        $pdo->commit();
        $full = adm_order_get((int) $id);
        if ($full) {
            adm_order_notify($full, 'Order ' . $o['order_number'] . ' cancelled',
                'Your order ' . $o['order_number'] . ' was cancelled. Reason: ' . $reason);
        }
        return [true, 'Order ' . $o['order_number'] . ' cancelled and stock restored.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('adm_order_cancel: ' . $e->getMessage());
        return [false, 'Could not cancel the order. Please try again.'];
    }
}

/* ---------------- coupons (AD-14) ---------------- */

function adm_coupons() {
    return db()->query('SELECT * FROM `coupons` ORDER BY `id` DESC LIMIT 200')->fetchAll();
}

function adm_coupon_save($id, $data, $actor_id) {
    $code = strtoupper(trim((string) ($data['code'] ?? '')));
    $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 150);
    $type = $data['type'] ?? 'fixed';
    $min_order = (int) round((float) ($data['min_order'] ?? 0) * 100);
    $max_disc = trim((string) ($data['max_discount'] ?? '')) === '' ? null : (int) round((float) $data['max_discount'] * 100);
    $usage = trim((string) ($data['usage_limit'] ?? '')) === '' ? null : (int) $data['usage_limit'];
    $active = isset($data['is_active']) ? 1 : 0;
    if (!preg_match('/^[A-Z0-9-]{3,40}$/', $code)) {
        return [false, 'Code must be 3–40 letters, digits or dashes.'];
    }
    if ($type === 'percent') {
        $value = (int) ($data['value_percent'] ?? 0);
        if ($value < 1 || $value > 100) {
            return [false, 'Percent value must be between 1 and 100.'];
        }
    } elseif ($type === 'fixed') {
        $value = (int) round((float) ($data['value_fixed'] ?? 0) * 100);
        if ($value < 1 || $value > 100000000) {
            return [false, 'Fixed discount looks invalid.'];
        }
    } else {
        return [false, 'Unknown coupon type.'];
    }
    if ($min_order < 0 || $min_order > 100000000) {
        return [false, 'Minimum order looks invalid.'];
    }
    if ($max_disc !== null && ($max_disc < 0 || $max_disc > 100000000)) {
        return [false, 'Maximum discount looks invalid.'];
    }
    if ($usage !== null && ($usage < 1 || $usage > 1000000)) {
        return [false, 'Usage limit looks invalid.'];
    }
    $starts = trim((string) ($data['starts_at'] ?? '')) ?: null;
    $ends = trim((string) ($data['ends_at'] ?? '')) ?: null;
    if (($starts && strtotime($starts) === false) || ($ends && strtotime($ends) === false)) {
        return [false, 'Coupon dates look invalid.'];
    }
    if ($starts && $ends && strtotime($ends) < strtotime($starts)) {
        return [false, 'End date cannot be before start date.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM `coupons` WHERE `code` = ? AND `id` <> ?');
    $stmt->execute([$code, (int) $id]);
    if ($stmt->fetchColumn()) {
        return [false, 'That coupon code is already in use.'];
    }
    $fields = [$code, $name ?: null, $type, $value, $min_order, $max_disc, $usage, $starts, $ends, $active];
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM `coupons` WHERE `id` = ?');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetchColumn()) {
            return [false, 'Coupon not found.'];
        }
        $pdo->prepare(
            'UPDATE `coupons` SET `code` = ?, `name` = ?, `type` = ?, `value` = ?, `min_order_minor` = ?,
             `max_discount_minor` = ?, `usage_limit` = ?, `starts_at` = ?, `ends_at` = ?, `is_active` = ? WHERE `id` = ?'
        )->execute(array_merge($fields, [(int) $id]));
        adm_audit('admin.coupon_update', $actor_id, (int) $id, null, ['code' => $code]);
        return [true, 'Coupon ' . $code . ' saved.'];
    }
    $pdo->prepare(
        'INSERT INTO `coupons` (`code`, `name`, `type`, `value`, `min_order_minor`, `max_discount_minor`,
         `usage_limit`, `starts_at`, `ends_at`, `is_active`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute($fields);
    adm_audit('admin.coupon_create', $actor_id, (int) $pdo->lastInsertId(), null, ['code' => $code]);
    return [true, 'Coupon ' . $code . ' created.'];
}

function adm_coupon_delete($id, $actor_id) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM `orders` WHERE `coupon_id` = ?');
    $stmt->execute([(int) $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return [false, 'That coupon has been used on orders and cannot be deleted — deactivate it instead.'];
    }
    $stmt = db()->prepare('DELETE FROM `coupons` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    if (!$stmt->rowCount()) {
        return [false, 'Coupon not found.'];
    }
    adm_audit('admin.coupon_delete', $actor_id, (int) $id, null, null);
    return [true, 'Coupon deleted.'];
}

/* ---------------- settings (AD-15…AD-17) ---------------- */

function adm_setting_groups() {
    return [
        'site' => ['site_name', 'tagline'],
        'contact' => ['contact_email', 'contact_phone', 'contact_address'],
        'locale' => ['currency', 'currency_symbol', 'timezone'],
        'orders' => ['orders_prefix', 'min_order_minor'],
        'wallet' => ['wallet_topup_min_minor', 'wallet_topup_max_minor', 'wallet_balance_cap_minor',
            'wallet_daily_topup_max_minor', 'wallet_daily_topup_count'],
        'payment' => ['bank_name', 'bank_account_name', 'bank_account_number', 'bank_instructions',
            'online_gateway_label'],
        'notifications' => ['notif_from_name', 'notif_from_email', 'admin_alert_email'],
    ];
}

function adm_settings($group = '') {
    $groups = adm_setting_groups();
    if ($group !== '' && !isset($groups[$group])) {
        return [];
    }
    $rows = db()->query('SELECT `key`, `value`, `group_name` FROM `settings`')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['key']] = $r['value'];
    }
    if ($group === '') {
        return [$groups, $out];
    }
    $filtered = [];
    foreach ($groups[$group] as $k) {
        $filtered[$k] = $out[$k] ?? '';
    }
    return [$groups, $filtered];
}

function adm_settings_save($group, $data, $actor_id) {
    $groups = adm_setting_groups();
    if (!isset($groups[$group])) {
        return [false, 'Unknown settings group.'];
    }
    $minor_naira = ['min_order_minor', 'wallet_topup_min_minor', 'wallet_topup_max_minor',
        'wallet_balance_cap_minor', 'wallet_daily_topup_max_minor'];
    $clean = [];
    foreach ($groups[$group] as $key) {
        $v = trim((string) ($data[$key] ?? ''));
        if (in_array($key, ['contact_email', 'notif_from_email', 'admin_alert_email'], true) && $v !== ''
            && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return [false, 'That email address for ' . $key . ' looks invalid.'];
        }
        if ($key === 'currency' && !preg_match('/^[A-Z]{3}$/', strtoupper($v))) {
            return [false, 'Currency must be a 3-letter code (e.g. NGN).'];
        }
        if ($key === 'timezone' && !in_array($v, timezone_identifiers_list(), true)) {
            return [false, 'Unknown timezone.'];
        }
        if (in_array($key, $minor_naira, true)) {
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 1000000000) {
                return [false, 'Amount for ' . $key . ' looks invalid.'];
            }
            $v = (string) (int) round((float) $v * 100);
        }
        if ($key === 'wallet_daily_topup_count' && (!ctype_digit($v) || (int) $v < 1 || (int) $v > 100)) {
            return [false, 'Daily top-up count must be between 1 and 100.'];
        }
        if (mb_strlen($v) > 2000) {
            return [false, 'Value for ' . $key . ' is too long.'];
        }
        $clean[$key] = $v;
    }
    if ($group === 'wallet'
        && (int) $clean['wallet_topup_min_minor'] > (int) $clean['wallet_topup_max_minor']) {
        return [false, 'Minimum top-up cannot exceed maximum top-up.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group_name` = VALUES(`group_name`)'
    );
    foreach ($clean as $k => $v) {
        $stmt->execute([$k, $v, $group]);
    }
    adm_audit('admin.settings', $actor_id, null, ['group' => $group], $clean);
    return [true, 'Settings saved.'];
}

/* ---------------- feature toggles (AD-18) ---------------- */

function adm_toggles() {
    return db()->query('SELECT * FROM `feature_toggles` ORDER BY `label`')->fetchAll();
}

function adm_toggle_set($key, $enabled, $actor_id) {
    $stmt = db()->prepare('SELECT `enabled`, `label` FROM `feature_toggles` WHERE `key` = ?');
    $stmt->execute([$key]);
    $t = $stmt->fetch();
    if (!$t) {
        return [false, 'Toggle not found.'];
    }
    db()->prepare('UPDATE `feature_toggles` SET `enabled` = ?, `updated_by` = ? WHERE `key` = ?')
        ->execute([$enabled ? 1 : 0, $actor_id ?: null, $key]);
    adm_audit('admin.toggle', $actor_id, null, ['key' => $key, 'was' => (int) $t['enabled']],
        ['key' => $key, 'now' => $enabled ? 1 : 0]);
    return [true, $t['label'] . ($enabled ? ' enabled.' : ' disabled.')];
}

/* ---------------- add-ons (AD-19) ---------------- */

function adm_addons() {
    return db()->query('SELECT * FROM `addons` ORDER BY `name`')->fetchAll();
}

function adm_addon_status($slug, $status, $actor_id) {
    $flow = [
        'registered' => ['installed'],
        'installed' => ['enabled', 'disabled'],
        'enabled' => ['disabled'],
        'disabled' => ['enabled'],
    ];
    if (!isset($flow[$status]) && !in_array($status, ['installed', 'enabled', 'disabled'], true)) {
        return [false, 'Unknown add-on status.'];
    }
    $stmt = db()->prepare('SELECT * FROM `addons` WHERE `slug` = ?');
    $stmt->execute([$slug]);
    $a = $stmt->fetch();
    if (!$a) {
        return [false, 'Add-on not found.'];
    }
    if (!in_array($status, $flow[$a['status']] ?? [], true)) {
        return [false, 'Cannot move an add-on from ' . $a['status'] . ' to ' . $status . '.'];
    }
    if ($status === 'installed') {
        db()->prepare("UPDATE `addons` SET `status` = 'installed', `installed_at` = NOW() WHERE `slug` = ?")
            ->execute([$slug]);
    } else {
        db()->prepare('UPDATE `addons` SET `status` = ? WHERE `slug` = ?')->execute([$status, $slug]);
    }
    adm_audit('admin.addon', $actor_id, (int) $a['id'], ['status' => $a['status']], ['status' => $status]);
    return [true, $a['name'] . ' is now ' . $status . '.'];
}

/* ---------------- dashboard ---------------- */

function adm_dashboard() {
    $pdo = db();
    $open = ['pending', 'confirmed', 'preparing', 'out_for_delivery'];
    $in = "'" . implode("','", $open) . "'";
    return [
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM `customers`')->fetchColumn(),
        'staff' => (int) $pdo->query("SELECT COUNT(*) FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`slug` <> 'customer'")->fetchColumn(),
        'drivers' => (int) $pdo->query("SELECT COUNT(*) FROM `drivers` WHERE `status` = 'active'")->fetchColumn(),
        'orders_open' => (int) $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `status` IN ($in)")->fetchColumn(),
        'orders_today' => (int) $pdo->query('SELECT COUNT(*) FROM `orders` WHERE DATE(`created_at`) = CURDATE()')->fetchColumn(),
        'revenue_minor' => (int) $pdo->query("SELECT COALESCE(SUM(`total_minor`), 0) FROM `orders` WHERE `payment_status` = 'paid'")->fetchColumn(),
        'refills_open' => (int) $pdo->query("SELECT COUNT(*) FROM `refills` WHERE `status` NOT IN ('completed','cancelled')")->fetchColumn(),
        'pickups_open' => (int) $pdo->query("SELECT COUNT(*) FROM `pickups` WHERE `status` NOT IN ('completed','cancelled')")->fetchColumn(),
        'low_stock' => (int) $pdo->query('SELECT COUNT(*) FROM `products` WHERE `track_inventory` = 1 AND `is_active` = 1 AND `stock_qty` <= `low_stock_at`')->fetchColumn(),
        'recent_orders' => $pdo->query(
            'SELECT o.`id`, o.`order_number`, o.`status`, o.`total_minor`, o.`created_at`, u.`name` AS customer_name
             FROM `orders` o JOIN `customers` c ON c.`id` = o.`customer_id`
             JOIN `users` u ON u.`id` = c.`user_id` ORDER BY o.`id` DESC LIMIT 5'
        )->fetchAll(),
    ];
}
