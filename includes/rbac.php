<?php
/**
 * Oyejo Gas - role-based access control (Phase 6).
 *
 * RULE: every protected page/action checks a PERMISSION slug, never a role
 * name. Roles are just bundles of permissions (see seeds + Phase 15 editor).
 * Guests (logged out) hold zero permissions. Denials are logged (R-14).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Permission slugs for a role (per-request static cache). Fails closed. */
function rbac_role_permissions($roleId) {
    static $cache = [];
    $roleId = (int) $roleId;
    if (!isset($cache[$roleId])) {
        $cache[$roleId] = [];
        try {
            $s = db()->prepare(
                'SELECT p.slug FROM `role_permissions` rp
                 JOIN `permissions` p ON p.id = rp.permission_id WHERE rp.role_id = ?'
            );
            $s->execute([$roleId]);
            foreach ($s->fetchAll() as $r) {
                $cache[$roleId][] = $r['slug'];
            }
        } catch (Throwable $t) {
            // DB unreachable: no permissions (fail closed).
        }
    }
    return $cache[$roleId];
}

/** Current visitor's permissions (session-cached, keyed by role). */
function rbac_my_permissions() {
    $u = current_user();
    if (!$u) {
        return [];
    }
    if (!isset($_SESSION['perms']) || ($_SESSION['perms_role'] ?? null) !== (int) $u['role_id']) {
        $_SESSION['perms'] = rbac_role_permissions((int) $u['role_id']);
        $_SESSION['perms_role'] = (int) $u['role_id'];
    }
    return $_SESSION['perms'];
}

/** Drop cached permissions (call after editing mappings — Phase 15). */
function rbac_refresh() {
    unset($_SESSION['perms'], $_SESSION['perms_role']);
}

function has_permission($permission) {
    return in_array($permission, rbac_my_permissions(), true);
}

function has_any_permission(array $permissions) {
    foreach ($permissions as $p) {
        if (has_permission($p)) {
            return true;
        }
    }
    return false;
}

function has_all_permissions(array $permissions) {
    foreach ($permissions as $p) {
        if (!has_permission($p)) {
            return false;
        }
    }
    return true;
}

function current_role() {
    $u = current_user();
    return $u ? (string) $u['role'] : 'guest';
}

/**
 * Gate a page/action on a permission. Guests are sent to login (with next);
 * logged-in users without it get a logged 403.
 */
function require_permission($permission) {
    if (!is_logged_in()) {
        require_login();
    }
    if (!has_permission($permission)) {
        rbac_log_denied($permission);
        http_response_code(403);
        $p = BASE_PATH . '/errors/403.php';
        if (is_readable($p)) {
            include $p;
        } else {
            echo 'Forbidden';
        }
        exit;
    }
}

/** Log denied access to audit_logs (+ error_log fallback). */
function rbac_log_denied($permission) {
    $u = current_user();
    $detail = json_encode([
        'permission' => $permission,
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'ip' => auth_ip(),
    ]);
    error_log('[oyejo] access denied: user=' . ($u['id'] ?? 'guest') . ' perm=' . $permission);
    try {
        db()->prepare(
            'INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `new_values`, `ip_address`)
             VALUES (?, \'access.denied\', \'permission\', ?, ?)'
        )->execute([$u ? (int) $u['id'] : null, $detail, mb_substr(auth_ip(), 0, 45)]);
    } catch (Throwable $t) {
        // Audit failure must never break the 403.
    }
}
