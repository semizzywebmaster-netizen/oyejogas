<?php
/**
 * Oyejo Gas - add-on engine (Phase 26, AO-01…AO-12).
 *
 * An add-on is one folder, `addons/<slug>/`, carrying an `addon.json`
 * manifest plus optional `pages/`, `migrations/`, `assets/` and README.
 * Lifecycle: scan/register → dependency check → install (migrations,
 * toggles, permissions, settings) → enable/disable → update. Every step
 * is permission-gated by the caller, logged to `addon_logs` and audited.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/admin.php'; // adm_audit for install/enable/update

function addon_base_dir() {
    return BASE_PATH . '/addons';
}

/** A valid add-on slug doubles as a safe folder name (no traversal). */
function addon_valid_slug($slug) {
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', (string) $slug);
}

function addon_dir($slug) {
    if (!addon_valid_slug($slug)) {
        return false;
    }
    $d = addon_base_dir() . '/' . $slug;
    return is_dir($d) ? $d : false;
}

/**
 * Read + validate a manifest. Returns [array|null, string $error].
 * $error is '' on success.
 */
function addon_read_manifest($slug) {
    $d = addon_dir($slug);
    if ($d === false) {
        return [null, 'Unknown add-on.'];
    }
    $f = $d . '/addon.json';
    if (!is_file($f)) {
        return [null, 'Manifest addon.json is missing.'];
    }
    $raw = @file_get_contents($f);
    $m = json_decode((string) $raw, true);
    if (!is_array($m)) {
        return [null, 'Manifest addon.json is not valid JSON.'];
    }
    if (($m['slug'] ?? '') !== $slug) {
        return [null, 'Manifest slug does not match its folder.'];
    }
    if (!preg_match('/^\S.{0,148}$/', (string) ($m['name'] ?? ''))) {
        return [null, 'Manifest needs a name (max 150 chars).'];
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? ''))) {
        return [null, 'Manifest version must look like 1.2.3.'];
    }
    foreach (['permissions', 'toggles', 'settings', 'menus'] as $list) {
        if (isset($m[$list]) && !is_array($m[$list])) {
            return [null, 'Manifest "' . $list . '" must be a list.'];
        }
    }
    foreach ((array) ($m['permissions'] ?? []) as $p) {
        if (!is_array($p) || !preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', (string) ($p['slug'] ?? ''))
            || trim((string) ($p['label'] ?? '')) === '') {
            return [null, 'Each manifest permission needs a dotted slug and a label.'];
        }
    }
    foreach ((array) ($m['toggles'] ?? []) as $t) {
        if (!is_array($t) || !preg_match('/^[a-z0-9_]{1,30}$/', (string) ($t['key'] ?? ''))
            || trim((string) ($t['label'] ?? '')) === '') {
            return [null, 'Each manifest toggle needs a short key and a label.'];
        }
        if (strlen('addon_' . $slug . '_' . $t['key']) > 60) {
            return [null, 'Toggle key for "' . $slug . '" is too long once namespaced.'];
        }
    }
    foreach ((array) ($m['settings'] ?? []) as $s) {
        if (!is_array($s) || !preg_match('/^[a-z0-9_]{1,40}$/', (string) ($s['key'] ?? ''))
            || trim((string) ($s['label'] ?? '')) === '') {
            return [null, 'Each manifest setting needs a short key and a label.'];
        }
    }
    foreach ((array) ($m['menus'] ?? []) as $mnu) {
        if (!is_array($mnu) || trim((string) ($mnu['label'] ?? '')) === ''
            || !preg_match('/^[a-z0-9_-]{1,40}$/', (string) ($mnu['page'] ?? ''))
            || trim((string) ($mnu['permission'] ?? '')) === '') {
            return [null, 'Each manifest menu needs a label, page and permission.'];
        }
    }
    return [$m, ''];
}

/** All on-disk manifests: slug => [manifest|null, error]. Skips _folders. */
function addon_scan() {
    $out = [];
    foreach ((array) glob(addon_base_dir() . '/*', GLOB_ONLYDIR) as $d) {
        $slug = basename($d);
        if ($slug === '' || $slug[0] === '_' || $slug[0] === '.') {
            continue;
        }
        if (!addon_valid_slug($slug)) {
            continue;
        }
        $out[$slug] = addon_read_manifest($slug);
    }
    ksort($out);
    return $out;
}

function addon_get($slug) {
    $stmt = db()->prepare('SELECT * FROM `addons` WHERE `slug` = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function addon_log($addon_id, $action, $detail, $actor_id) {
    db()->prepare('INSERT INTO `addon_logs` (`addon_id`, `actor_id`, `action`, `detail`) VALUES (?, ?, ?, ?)')
        ->execute([(int) $addon_id, $actor_id ? (int) $actor_id : null, (string) $action, (string) $detail]);
}

function addon_logs($addon_id, $limit = 100) {
    $stmt = db()->prepare(
        'SELECT l.*, u.`name` AS actor FROM `addon_logs` l LEFT JOIN `users` u ON u.`id` = l.`actor_id`
         WHERE l.`addon_id` = ? ORDER BY l.`id` DESC LIMIT ' . max(1, min(500, (int) $limit))
    );
    $stmt->execute([(int) $addon_id]);
    return $stmt->fetchAll();
}

/**
 * Register on-disk add-ons and refresh manifest versions (AO-01/AO-02).
 * Returns ['registered' => n, 'updated' => n, 'errors' => [slug => err]].
 */
function addon_sync($actor_id) {
    $registered = 0;
    $updated = 0;
    $errors = [];
    foreach (addon_scan() as $slug => [$m, $err]) {
        if ($m === null) {
            $errors[$slug] = $err;
            continue;
        }
        $row = addon_get($slug);
        if ($row === null) {
            db()->prepare('INSERT INTO `addons` (`slug`, `name`, `version`, `status`) VALUES (?, ?, ?, \'registered\')')
                ->execute([$slug, $m['name'], $m['version']]);
            addon_log((int) db()->lastInsertId(), 'registered', 'v' . $m['version'] . ' registered from manifest.', $actor_id);
            $registered++;
        } elseif ($row['version'] !== $m['version'] || $row['name'] !== $m['name']) {
            db()->prepare('UPDATE `addons` SET `name` = ?, `version` = ? WHERE `slug` = ?')
                ->execute([$m['name'], $m['version'], $slug]);
            addon_log((int) $row['id'], 'manifest_updated', 'Manifest now v' . $m['version'] . '.', $actor_id);
            $updated++;
        }
    }
    if ($registered || $updated) {
        adm_audit('admin.addons_scan', $actor_id, null, [], ['registered' => $registered, 'updated' => $updated]);
    }
    return ['registered' => $registered, 'updated' => $updated, 'errors' => $errors];
}

/** DB rows enriched with on-disk/update state for the desk. */
function addon_list() {
    $rows = db()->query('SELECT * FROM `addons` ORDER BY `name`')->fetchAll();
    foreach ($rows as &$r) {
        [$m, $err] = addon_read_manifest($r['slug']);
        $r['on_disk'] = $m !== null;
        $r['manifest_error'] = $m === null ? $err : '';
        $r['update_available'] = $m !== null && $r['installed_version'] !== null
            && version_compare($m['version'], $r['installed_version'], '>');
        $r['pending_migrations'] = 0;
        if ($m !== null && $r['status'] !== 'registered') {
            [, $pending] = addon_migrations_status($r['slug']);
            $r['pending_migrations'] = count($pending);
        }
    }
    return $rows;
}

/* ---------------- dependency checks (AO-08) ---------------- */

function addon_version_satisfies($have, $constraint) {
    $constraint = trim((string) $constraint);
    if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+\.\d+(?:\.\d+)?)$/', $constraint, $mm)) {
        $op = $mm[1] === '' ? '=' : $mm[1];
        return version_compare($have, $mm[2], $op);
    }
    return false;
}

/** Pure check over a manifest array: [ok, issues[]]. */
function addon_check_manifest($m) {
    $issues = [];
    $req = (array) ($m['requires'] ?? []);
    if (isset($req['oyejo']) && !addon_version_satisfies(OYEJO_VERSION, $req['oyejo'])) {
        $issues[] = 'Needs Oyejo platform ' . $req['oyejo'] . ' (have ' . OYEJO_VERSION . ').';
    }
    if (isset($req['php']) && !addon_version_satisfies(PHP_VERSION, $req['php'])) {
        $issues[] = 'Needs PHP ' . $req['php'] . ' (have ' . PHP_VERSION . ').';
    }
    foreach ((array) ($req['addons'] ?? []) as $dep) {
        $d = addon_get((string) $dep);
        if ($d === null || !in_array($d['status'], ['installed', 'enabled', 'disabled'], true)) {
            $issues[] = 'Needs the "' . $dep . '" add-on installed first.';
        } elseif ($d['status'] !== 'enabled') {
            $issues[] = 'Needs the "' . $dep . '" add-on enabled (now ' . $d['status'] . ').';
        }
    }
    return [$issues === [], $issues];
}

function addon_check($slug) {
    [$m, $err] = addon_read_manifest($slug);
    if ($m === null) {
        return [false, [$err]];
    }
    return addon_check_manifest($m);
}

/* ---------------- migrations (AO-06) ---------------- */

/** Split SQL into statements, respecting quotes and -- / # / block comments. */
function addon_sql_split($sql) {
    $stmts = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {
                $buf .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === '-' && $next === '-' && ($i + 2 >= $len || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t")) {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $buf .= "\n";
            continue;
        }
        if ($ch === '#') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $buf .= "\n";
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $i += 2;
            while ($i < $len && !($sql[$i] === '*' && $i + 1 < $len && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            continue;
        }
        if ($ch === ';') {
            if (trim($buf) !== '') {
                $stmts[] = $buf;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $stmts[] = $buf;
    }
    return $stmts;
}

function addon_migration_files($slug) {
    $d = addon_dir($slug);
    if ($d === false) {
        return [];
    }
    $files = [];
    foreach ((array) glob($d . '/migrations/*.sql') as $f) {
        $base = basename($f);
        if (preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $base)) {
            $files[] = $base;
        }
    }
    sort($files);
    return $files;
}

/** [applied[], pending[]] filenames for an add-on. */
function addon_migrations_status($slug) {
    $row = addon_get($slug);
    $applied = [];
    if ($row !== null) {
        $stmt = db()->prepare('SELECT `filename` FROM `addon_migrations` WHERE `addon_id` = ? ORDER BY `filename`');
        $stmt->execute([(int) $row['id']]);
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    $pending = array_values(array_diff(addon_migration_files($slug), $applied));
    return [$applied, $pending];
}

/** Run pending migrations. Returns [ok, applied[], error]. */
function addon_migrate($slug, $actor_id) {
    $row = addon_get($slug);
    if ($row === null) {
        return [false, [], 'Add-on not found.'];
    }
    $d = addon_dir($slug);
    if ($d === false) {
        return [false, [], 'Add-on files are missing.'];
    }
    [, $pending] = addon_migrations_status($slug);
    $applied = [];
    foreach ($pending as $file) {
        $stmts = addon_sql_split((string) @file_get_contents($d . '/migrations/' . $file));
        try {
            foreach ($stmts as $sql) {
                db()->exec($sql);
            }
            db()->prepare('INSERT INTO `addon_migrations` (`addon_id`, `filename`) VALUES (?, ?)')
                ->execute([(int) $row['id'], $file]);
            $applied[] = $file;
        } catch (Throwable $t) {
            addon_log((int) $row['id'], 'migration_failed', $file . ': ' . $t->getMessage(), $actor_id);
            return [false, $applied, 'Migration ' . $file . ' failed: ' . $t->getMessage()];
        }
    }
    if ($applied !== []) {
        addon_log((int) $row['id'], 'migrations', 'Applied: ' . implode(', ', $applied), $actor_id);
    }
    return [true, $applied, ''];
}

/* ---------------- install / status / update ---------------- */

function addon_grant_roles(array $perm_ids) {
    if ($perm_ids === []) {
        return;
    }
    $pdo = db();
    $roles = $pdo->query("SELECT `id` FROM `roles` WHERE `slug` IN ('super_admin', 'admin')")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare('INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)');
    foreach ($roles as $rid) {
        foreach ($perm_ids as $pid) {
            $stmt->execute([(int) $rid, (int) $pid]);
        }
    }
}

/**
 * Install: deps → migrations → toggles → permissions (+grants) →
 * settings. registered → installed. Returns [ok, message].
 */
function addon_install($slug, $actor_id) {
    [$m, $err] = addon_read_manifest($slug);
    if ($m === null) {
        return [false, $err];
    }
    $row = addon_get($slug);
    if ($row === null) {
        return [false, 'Add-on is not registered. Scan for add-ons first.'];
    }
    if ($row['status'] !== 'registered') {
        return [false, 'Only registered add-ons can be installed (now ' . $row['status'] . ').'];
    }
    [$ok, $issues] = addon_check_manifest($m);
    if (!$ok) {
        return [false, 'Dependencies not met: ' . implode(' ', $issues)];
    }
    [$mok, $applied, $merr] = addon_migrate($slug, $actor_id);
    if (!$mok) {
        return [false, $merr];
    }
    $pdo = db();
    $toggles = 0;
    foreach ((array) ($m['toggles'] ?? []) as $t) {
        $key = 'addon_' . $slug . '_' . $t['key'];
        $pdo->prepare('INSERT IGNORE INTO `feature_toggles` (`key`, `label`, `description`, `enabled`, `updated_by`)
                        VALUES (?, ?, ?, ?, ?)')
            ->execute([$key, $t['label'], $m['name'], !isset($t['default']) || (bool) $t['default'] ? 1 : 0, $actor_id ?: null]);
        $toggles++;
    }
    $perm_ids = [];
    $perms = 0;
    foreach ((array) ($m['permissions'] ?? []) as $p) {
        $pdo->prepare('INSERT IGNORE INTO `permissions` (`slug`, `name`, `group_name`) VALUES (?, ?, ?)')
            ->execute([$p['slug'], $p['label'], 'addon:' . $slug]);
        $pid = (int) $pdo->query('SELECT `id` FROM `permissions` WHERE `slug` = ' . $pdo->quote($p['slug']))->fetchColumn();
        if ($pid) {
            $perm_ids[] = $pid;
        }
        $perms++;
    }
    addon_grant_roles($perm_ids);
    $settings = 0;
    foreach ((array) ($m['settings'] ?? []) as $s) {
        $key = 'addon_' . $slug . '_' . $s['key'];
        $pdo->prepare('INSERT IGNORE INTO `settings` (`key`, `value`, `group_name`) VALUES (?, ?, ?)')
            ->execute([$key, (string) ($s['default'] ?? ''), 'addon_' . $slug]);
        $settings++;
    }
    $pdo->prepare('UPDATE `addons` SET `status` = \'installed\', `installed_version` = ?, `installed_at` = NOW() WHERE `slug` = ?')
        ->execute([$m['version'], $slug]);
    $detail = count($applied) . ' migration(s), ' . $toggles . ' toggle(s), ' . $perms . ' permission(s), '
        . $settings . ' setting(s).';
    addon_log((int) $row['id'], 'installed', 'v' . $m['version'] . '. ' . $detail, $actor_id);
    adm_audit('admin.addon', $actor_id, (int) $row['id'], ['status' => 'registered'], ['status' => 'installed']);
    return [true, $m['name'] . ' installed (' . $detail . ')'];
}

/** Enable/disable transitions. Returns [ok, message]. */
function addon_set_status($slug, $to, $actor_id) {
    $flow = [
        'installed' => ['enabled', 'disabled'],
        'enabled' => ['disabled'],
        'disabled' => ['enabled'],
    ];
    if (!in_array($to, ['enabled', 'disabled'], true)) {
        return [false, 'Unknown add-on status.'];
    }
    $row = addon_get($slug);
    if ($row === null) {
        return [false, 'Add-on not found.'];
    }
    if (!in_array($to, $flow[$row['status']] ?? [], true)) {
        return [false, 'Cannot move an add-on from ' . $row['status'] . ' to ' . $to . '.'];
    }
    if ($to === 'enabled') {
        [$m, $err] = addon_read_manifest($slug);
        if ($m === null) {
            return [false, 'Cannot enable: ' . $err];
        }
        [$ok, $issues] = addon_check_manifest($m);
        if (!$ok) {
            return [false, 'Cannot enable: ' . implode(' ', $issues)];
        }
    }
    db()->prepare('UPDATE `addons` SET `status` = ? WHERE `slug` = ?')->execute([$to, $slug]);
    addon_log((int) $row['id'], $to, 'Status changed from ' . $row['status'] . '.', $actor_id);
    adm_audit('admin.addon', $actor_id, (int) $row['id'], ['status' => $row['status']], ['status' => $to]);
    return [true, $row['name'] . ' is now ' . $to . '.'];
}

/** Run pending migrations + record the manifest version. */
function addon_update($slug, $actor_id) {
    [$m, $err] = addon_read_manifest($slug);
    if ($m === null) {
        return [false, $err];
    }
    $row = addon_get($slug);
    if ($row === null || $row['status'] === 'registered') {
        return [false, 'Only installed add-ons can be updated.'];
    }
    if ($row['installed_version'] !== null && !version_compare($m['version'], $row['installed_version'], '>')) {
        return [false, $m['name'] . ' is already up to date (v' . $row['installed_version'] . ').'];
    }
    [$mok, $applied, $merr] = addon_migrate($slug, $actor_id);
    if (!$mok) {
        return [false, $merr];
    }
    db()->prepare('UPDATE `addons` SET `installed_version` = ?, `version` = ?, `name` = ? WHERE `slug` = ?')
        ->execute([$m['version'], $m['version'], $m['name'], $slug]);
    $msg = 'Updated to v' . $m['version'] . ' (' . count($applied) . ' new migration(s)).';
    addon_log((int) $row['id'], 'updated', $msg, $actor_id);
    adm_audit('admin.addon', $actor_id, (int) $row['id'],
        ['installed_version' => $row['installed_version']], ['installed_version' => $m['version']]);
    return [true, $msg];
}

/** True when the system toggle is on and this add-on is enabled. */
function addon_on($slug) {
    if (!oyejo_feature('addons')) {
        return false;
    }
    $row = addon_get($slug);
    return $row !== null && $row['status'] === 'enabled'
        && addon_read_manifest($slug)[0] !== null;
}

/* ---------------- menus + pages (AO-05) ---------------- */

/** Console entries from enabled add-ons: [label, path, permission]. */
function addon_menus() {
    if (!oyejo_feature('addons')) {
        return [];
    }
    $rows = db()->query("SELECT * FROM `addons` WHERE `status` = 'enabled' ORDER BY `name`")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        [$m] = addon_read_manifest($r['slug']);
        if ($m === null) {
            continue;
        }
        foreach ((array) ($m['menus'] ?? []) as $mnu) {
            $out[] = [
                $mnu['label'],
                'admin/addon.php?addon=' . $r['slug'] . '&page=' . $mnu['page'],
                $mnu['permission'],
            ];
        }
    }
    return $out;
}

/**
 * Resolve an add-on page include. Returns [path|null, permission, error].
 * Only pages declared in the manifest menus are reachable.
 */
function addon_page_file($slug, $page) {
    if (!oyejo_feature('addons')) {
        return [null, '', 'The add-on system is disabled.'];
    }
    if (!addon_valid_slug($slug) || !preg_match('/^[a-z0-9_-]{1,40}$/', (string) $page)) {
        return [null, '', 'Unknown add-on page.'];
    }
    $row = addon_get($slug);
    if ($row === null || $row['status'] !== 'enabled') {
        return [null, '', 'Add-on is not enabled.'];
    }
    [$m, $err] = addon_read_manifest($slug);
    if ($m === null) {
        return [null, '', $err];
    }
    $need = '';
    foreach ((array) ($m['menus'] ?? []) as $mnu) {
        if ($mnu['page'] === $page) {
            $need = $mnu['permission'];
        }
    }
    if ($need === '') {
        return [null, '', 'Page is not declared in the manifest.'];
    }
    $d = addon_dir($slug);
    $f = $d . '/pages/' . $page . '.php';
    $real = realpath($f);
    if ($real === false || strpos($real, realpath($d . '/pages') ?: $d) !== 0 || substr($real, -4) !== '.php') {
        return [null, '', 'Page file not found.'];
    }
    return [$real, $need, ''];
}

/* ---------------- settings groups (AO-03) ---------------- */

/** Extra `adm_setting_groups()` entries for installed add-ons. */
function addon_setting_groups() {
    $rows = db()->query("SELECT * FROM `addons` WHERE `status` <> 'registered' ORDER BY `name`")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        [$m] = addon_read_manifest($r['slug']);
        if ($m === null || ($m['settings'] ?? []) === []) {
            continue;
        }
        $keys = [];
        foreach ((array) $m['settings'] as $s) {
            $keys[] = 'addon_' . $r['slug'] . '_' . $s['key'];
        }
        $out['addon_' . $r['slug']] = $keys;
    }
    return $out;
}
