<?php
/**
 * Oyejo Gas - installer library (Phase 4).
 * Standalone: must NOT load bootstrap (the app may not be installed yet).
 * Only loaded via install/index.php.
 */
if (!defined('OYEJO_INSTALL')) {
    http_response_code(403);
    exit('Forbidden');
}

function inst_base_path() {
    return dirname(__DIR__);
}

function inst_lock_path() {
    return inst_base_path() . '/storage/install.lock';
}

function inst_is_installed() {
    return is_file(inst_lock_path());
}

/** Pre-flight environment checks. Each: [label, ok, detail]. */
function inst_preflight() {
    $base = inst_base_path();
    $envOk = (!is_file($base . '/.env') && is_writable($base))
        || (is_file($base . '/.env') && is_writable($base . '/.env'));
    return [
        ['PHP >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), 'Running PHP ' . PHP_VERSION],
        ['PDO MySQL driver', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'available' : 'enable the pdo_mysql extension'],
        ['JSON support', function_exists('json_encode'), function_exists('json_encode') ? 'available' : 'enable the json extension'],
        ['Multibyte strings', function_exists('mb_substr'), function_exists('mb_substr') ? 'available' : 'enable the mbstring extension'],
        ['Password hashing', function_exists('password_hash'), function_exists('password_hash') ? 'available' : 'password_hash() missing'],
        ['storage/ writable', is_writable($base . '/storage'), $base . '/storage'],
        ['uploads/ writable', is_writable($base . '/uploads'), $base . '/uploads'],
        ['.env writable', $envOk, $envOk ? 'installer can write .env' : 'make the folder or .env writable'],
        ['database/schema.sql readable', is_readable($base . '/database/schema.sql'), 'base schema'],
        ['database/seeds.sql readable', is_readable($base . '/database/seeds.sql'), 'seed data'],
    ];
}

/** Quote a value for the .env format. */
function inst_env_value($v) {
    $v = (string) $v;
    if ($v !== '' && preg_match('/^[A-Za-z0-9_@:\/.\-]+$/', $v)) {
        return $v;
    }
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
}

/** Write .env from the template + supplied values. Returns [ok, error]. */
function inst_write_env($data) {
    $base = inst_base_path();
    $tpl = @file_get_contents($base . '/.env.example');
    if ($tpl === false) {
        return [false, 'Could not read .env.example'];
    }
    $map = [
        'APP_NAME' => $data['site_name'],
        'APP_KEY' => bin2hex(random_bytes(32)),
        'APP_URL' => $data['site_url'],
        'APP_DEBUG' => $data['debug'] ? 'true' : 'false',
        'APP_TIMEZONE' => 'Africa/Lagos',
        'DB_HOST' => $data['db_host'],
        'DB_PORT' => $data['db_port'],
        'DB_NAME' => $data['db_name'],
        'DB_USER' => $data['db_user'],
        'DB_PASS' => $data['db_pass'],
    ];
    foreach ($map as $k => $v) {
        $line = $k . '=' . inst_env_value($v);
        if (preg_match('/^' . preg_quote($k, '/') . '=.*$/m', $tpl)) {
            $tpl = preg_replace('/^' . preg_quote($k, '/') . '=.*$/m', $line, $tpl);
        } else {
            $tpl .= "\n" . $line . "\n";
        }
    }
    if (@file_put_contents($base . '/.env', $tpl, LOCK_EX) === false) {
        return [false, 'Could not write .env (check folder permissions)'];
    }
    return [true, ''];
}

/**
 * Split SQL into statements, honoring DELIMITER directives, quoted strings
 * and -- / # / block comments. PDO cannot run DELIMITER itself.
 */
function inst_split_sql($sql) {
    $stmts = [];
    $buf = '';
    $delim = ';';
    $inS = false;
    $inD = false;
    $inB = false;
    $lines = preg_split("/\r\n|\n|\r/", $sql);
    foreach ($lines as $line) {
        $t = ltrim($line);
        if (!$inS && !$inD && !$inB && preg_match('/^DELIMITER\s+(\S+)\s*$/i', $t, $m)) {
            $delim = $m[1];
            continue;
        }
        if (!$inS && !$inD && !$inB
            && ($t === '' || strpos($t, '-- ') === 0 || $t === '--' || strpos($t, '#') === 0)
        ) {
            continue;
        }
        $out = '';
        $n = strlen($line);
        for ($i = 0; $i < $n; $i++) {
            $c = $line[$i];
            $nx = ($i + 1 < $n) ? $line[$i + 1] : '';
            if ($inB) {
                if ($c === '*' && $nx === '/') {
                    $inB = false;
                    $i++;
                }
                continue;
            }
            if ($inS) {
                $out .= $c;
                if ($c === '\\' && $nx !== '') {
                    $out .= $nx;
                    $i++;
                } elseif ($c === "'") {
                    if ($nx === "'") {
                        $out .= $nx;
                        $i++;
                    } else {
                        $inS = false;
                    }
                }
                continue;
            }
            if ($inD) {
                $out .= $c;
                if ($c === '\\' && $nx !== '') {
                    $out .= $nx;
                    $i++;
                } elseif ($c === '"') {
                    $inD = false;
                }
                continue;
            }
            if ($c === '/' && $nx === '*') {
                $inB = true;
                $i++;
                continue;
            }
            if ($c === "'") {
                $inS = true;
                $out .= $c;
                continue;
            }
            if ($c === '"') {
                $inD = true;
                $out .= $c;
                continue;
            }
            $after = ($i + 2 < $n) ? $line[$i + 2] : ' ';
            if (($c === '-' && $nx === '-' && ($after === ' ' || $after === "\t")) || $c === '#') {
                break;
            }
            $out .= $c;
        }
        $buf .= $out . "\n";
        if (!$inS && !$inD && !$inB) {
            $r = rtrim($buf);
            $dl = strlen($delim);
            if ($dl > 0 && substr($r, -$dl) === $delim) {
                $stmt = trim(substr($r, 0, -$dl));
                if ($stmt !== '') {
                    $stmts[] = $stmt;
                }
                $buf = '';
            }
        }
    }
    $tail = trim($buf);
    if ($tail !== '' && $tail !== $delim) {
        $stmts[] = $tail;
    }
    return $stmts;
}

/** Execute every statement in a .sql file. Returns [statements_run, error]. */
function inst_import_file($pdo, $path) {
    $sql = @file_get_contents($path);
    if ($sql === false) {
        return [0, 'Could not read ' . basename($path)];
    }
    $stmts = inst_split_sql($sql);
    $n = 0;
    foreach ($stmts as $stmt) {
        $n++;
        try {
            $pdo->exec($stmt);
        } catch (Throwable $t) {
            return [$n - 1, basename($path) . ' statement ' . $n . ': ' . $t->getMessage()];
        }
    }
    return [$n, ''];
}

function inst_pdo_options() {
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];
}

/** Connect to the server (no database selected). Returns [pdo|null, error]. */
function inst_connect_server($host, $port, $user, $pass) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $user,
            $pass,
            inst_pdo_options()
        );
        return [$pdo, ''];
    } catch (Throwable $t) {
        return [null, 'Could not connect to MySQL: ' . $t->getMessage()];
    }
}

/** Connect to a specific database. Returns [pdo|null, error]. */
function inst_connect_db($host, $port, $name, $user, $pass) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $pass,
            inst_pdo_options()
        );
        return [$pdo, ''];
    } catch (Throwable $t) {
        return [null, 'Could not select database "' . $name . '": ' . $t->getMessage()];
    }
}

/** Create the super-admin account. Returns [id|null, error]. */
function inst_create_admin($pdo, $name, $email, $phone, $password) {
    $row = $pdo->query("SELECT `id` FROM `roles` WHERE `slug` = 'super_admin'")->fetch();
    if (!$row) {
        return [null, 'super_admin role missing (did seeds import?)'];
    }
    $chk = $pdo->prepare('SELECT `id` FROM `users` WHERE `email` = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        return [null, 'An account with that email already exists. Empty the database and retry.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO `users` (`role_id`, `name`, `email`, `phone`, `password_hash`, `status`, `email_verified_at`)
         VALUES (?, ?, ?, ?, ?, \'active\', NOW())'
    );
    $stmt->execute([$row['id'], $name, $email, $phone !== '' ? $phone : null, $hash]);
    return [(int) $pdo->lastInsertId(), ''];
}

/** Post-install validation rows: [label, expected, got, ok]. */
function inst_validate($pdo, $dbName) {
    $count = function ($table) use ($pdo) {
        return (int) $pdo->query('SELECT COUNT(*) AS c FROM `' . $table . '`')->fetch()['c'];
    };
    $tables = (int) $pdo->query(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ' . $pdo->quote($dbName)
    )->fetch()['c'];
    $admins = (int) $pdo->query(
        "SELECT COUNT(*) AS c FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`slug` = 'super_admin'"
    )->fetch()['c'];
    $perms = $count('permissions');
    $settings = $count('settings');
    $base = inst_base_path();
    return [
        ['Tables created', '60', (string) $tables, $tables === 60],
        ['Roles seeded', '12', (string) $count('roles'), $count('roles') === 12],
        ['Permissions seeded', '>= 70', (string) $perms, $perms >= 70],
        ['Feature toggles seeded', '25', (string) $count('feature_toggles'), $count('feature_toggles') === 25],
        ['Settings seeded', '>= 10', (string) $settings, $settings >= 10],
        ['Categories seeded', '>= 4', (string) $count('categories'), $count('categories') >= 4],
        ['Cylinder sizes seeded', '5', (string) $count('cylinder_sizes'), $count('cylinder_sizes') === 5],
        ['Delivery zones seeded', '>= 3', (string) $count('delivery_zones'), $count('delivery_zones') >= 3],
        ['Delivery slots seeded', '3', (string) $count('delivery_slots'), $count('delivery_slots') === 3],
        ['Super-admin accounts', '>= 1', (string) $admins, $admins >= 1],
        ['.env written', 'yes', is_file($base . '/.env') ? 'yes' : 'no', is_file($base . '/.env')],
        ['install.lock written', 'yes', inst_is_installed() ? 'yes' : 'no', inst_is_installed()],
    ];
}

/** Run the full installation. Returns result array with steps + validation. */
function inst_run($in) {
    $steps = [];
    $errors = [];
    $fail = function ($msg) use (&$errors) {
        $errors[] = $msg;
        return ['ok' => false, 'steps' => [], 'validation' => [], 'errors' => $errors];
    };

    list($ok, $err) = inst_write_env($in);
    if (!$ok) {
        return $fail($err);
    }
    $steps[] = 'Environment file (.env) written';

    list($server, $err) = inst_connect_server($in['db_host'], $in['db_port'], $in['db_user'], $in['db_pass']);
    if (!$server) {
        return $fail($err . ' Check the host, port, username and password.');
    }
    $steps[] = 'Connected to MySQL server';

    if ($in['db_create']) {
        try {
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $in['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $steps[] = 'Database "' . $in['db_name'] . '" ready';
        } catch (Throwable $t) {
            return $fail('Could not create database: ' . $t->getMessage() . ' Create it manually and untick "create database".');
        }
    }

    list($pdo, $err) = inst_connect_db($in['db_host'], $in['db_port'], $in['db_name'], $in['db_user'], $in['db_pass']);
    if (!$pdo) {
        return $fail($err);
    }

    $base = inst_base_path();
    list($n, $err) = inst_import_file($pdo, $base . '/database/schema.sql');
    if ($err !== '') {
        return $fail($err . ' Empty the database and retry.');
    }
    $steps[] = 'Schema imported (' . $n . ' statements)';

    list($n, $err) = inst_import_file($pdo, $base . '/database/seeds.sql');
    if ($err !== '') {
        return $fail($err . ' Empty the database and retry.');
    }
    $steps[] = 'Seed data imported (' . $n . ' statements)';

    list($adminId, $err) = inst_create_admin(
        $pdo,
        $in['admin_name'],
        $in['admin_email'],
        $in['admin_phone'],
        $in['admin_pass']
    );
    if (!$adminId) {
        return $fail($err);
    }
    $steps[] = 'Super-admin account created (#' . $adminId . ')';

    $lockBody = 'Installed ' . date('Y-m-d H:i:s') . ' by ' . $in['admin_email'] . "\n"
        . 'Delete this file (and empty the database) to reinstall. '
        . 'On a live site, delete the install/ folder instead.' . "\n";
    if (@file_put_contents(inst_lock_path(), $lockBody, LOCK_EX) === false) {
        return $fail('Database is ready but install.lock could not be written. Check storage/ permissions.');
    }
    $steps[] = 'Installation locked (reinstall protection active)';

    $validation = inst_validate($pdo, $in['db_name']);
    foreach ($validation as $row) {
        if (!$row[3]) {
            return ['ok' => false, 'steps' => $steps, 'validation' => $validation,
                'errors' => ['Validation failed on: ' . $row[0] . ' (expected ' . $row[1] . ', got ' . $row[2] . ')']];
        }
    }
    return ['ok' => true, 'steps' => $steps, 'validation' => $validation, 'errors' => []];
}

/** Minimal standalone page shell for the installer. */
function inst_page_top($title) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title) . ' — Oyejo Gas installer</title>'
        . '<link rel="stylesheet" href="../assets/css/style.css">'
        . '<style>.inst{max-width:720px;margin:32px auto}.inst .card{margin-bottom:20px}'
        . '.ok{color:#0b6b3a;font-weight:700}.bad{color:#8f1d1d;font-weight:700}'
        . 'table.data td:last-child{text-align:right}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}'
        . '@media(max-width:640px){.grid2{grid-template-columns:1fr}}</style>'
        . '</head><body><main class="wrap inst">';
}

function inst_page_bottom() {
    echo '</main></body></html>';
}

function inst_h($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
