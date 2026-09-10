<?php
/**
 * Oyejo Gas - one-click installer (Phase 4).
 * Standalone: never loads bootstrap (the app may not be installed yet).
 */
define('OYEJO_INSTALL', true);
require_once __DIR__ . '/installer.php';
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Reinstall protection: a locked site never runs the installer ---
if (inst_is_installed()) {
    http_response_code(403);
    inst_page_top('Already installed');
    echo '<div class="card"><h1>Already installed</h1>'
        . '<p class="bad">The installer is locked. This protects your live site from accidental reinstallation.</p>'
        . '<p>To reinstall deliberately: delete <code>storage/install.lock</code>, empty the database, then reload this page.</p>'
        . '<p>On a live site the safest option is to delete the <code>install/</code> folder entirely.</p>'
        . '<p><a class="btn ghost" href="../">&larr; Back to site</a></p></div>';
    inst_page_bottom();
    exit;
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['install_csrf'];
$old = $_SESSION['install_old'] ?? [];
$errors = [];
$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'install') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {
        $in = [
            'site_name' => trim((string) ($_POST['site_name'] ?? '')),
            'site_url' => rtrim(trim((string) ($_POST['site_url'] ?? '')), '/'),
            'db_host' => trim((string) ($_POST['db_host'] ?? '')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '')),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_pass' => (string) ($_POST['db_pass'] ?? ''),
            'db_create' => !empty($_POST['db_create']),
            'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
            'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
            'admin_phone' => trim((string) ($_POST['admin_phone'] ?? '')),
            'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
            'debug' => !empty($_POST['debug']),
        ];
        $_SESSION['install_old'] = $in;
        unset($_SESSION['install_old']['db_pass'], $_SESSION['install_old']['admin_pass']);

        if (strlen($in['site_name']) < 2 || strlen($in['site_name']) > 60) {
            $errors[] = 'Site name must be 2–60 characters.';
        }
        if (!filter_var($in['site_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Site URL must be a valid URL (e.g. https://example.com).';
        }
        if ($in['db_host'] === '' || strlen($in['db_host']) > 100) {
            $errors[] = 'Database host is required.';
        }
        if (!ctype_digit($in['db_port']) || (int) $in['db_port'] < 1 || (int) $in['db_port'] > 65535) {
            $errors[] = 'Database port must be 1–65535.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $in['db_name'])) {
            $errors[] = 'Database name may only contain letters, numbers and underscores.';
        }
        if ($in['db_user'] === '' || strlen($in['db_user']) > 64) {
            $errors[] = 'Database username is required.';
        }
        if (strlen($in['admin_name']) < 2 || strlen($in['admin_name']) > 100) {
            $errors[] = 'Admin name must be 2–100 characters.';
        }
        if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Admin email must be valid.';
        }
        if ($in['admin_phone'] !== '' && !preg_match('/^[0-9+\s()\-]{7,20}$/', $in['admin_phone'])) {
            $errors[] = 'Admin phone looks invalid.';
        }
        if (strlen($in['admin_pass']) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }

        if (!$errors) {
            $result = inst_run($in);
            if (!$result['ok']) {
                $errors = $result['errors'];
            } else {
                unset($_SESSION['install_old']);
            }
        }
    }
}

$checks = inst_preflight();
$canInstall = true;
foreach ($checks as $c) {
    if (!$c[1]) {
        $canInstall = false;
    }
}
$v = function ($k, $dflt = '') use ($old) {
    return inst_h($old[$k] ?? $dflt);
};

inst_page_top('Installer');
echo '<p class="pill">Oyejo Gas &middot; one-click installer</p><h1>Install Oyejo Gas</h1>';

foreach ($errors as $e) {
    echo '<div class="alert alert-error">' . inst_h($e) . '</div>';
}

if ($result && $result['ok']) {
    echo '<div class="card"><h2 class="ok">Installation complete</h2><ul class="ticks">';
    foreach ($result['steps'] as $s) {
        echo '<li>' . inst_h($s) . '</li>';
    }
    echo '</ul></div>';
    echo '<div class="card"><h2>Validation</h2><table class="data"><tr><th>Check</th><th>Expected</th><th>Got</th><th>Status</th></tr>';
    foreach ($result['validation'] as $row) {
        echo '<tr><td>' . inst_h($row[0]) . '</td><td>' . inst_h($row[1]) . '</td><td>' . inst_h($row[2])
            . '</td><td class="' . ($row[3] ? 'ok' : 'bad') . '">' . ($row[3] ? 'PASS' : 'FAIL') . '</td></tr>';
    }
    echo '</table></div>';
    echo '<div class="card"><h2>Next steps</h2><ul>'
        . '<li>On a live site, <strong>delete the <code>install/</code> folder</strong> now.</li>'
        . '<li>The admin login screen arrives in Phase 5 — your super-admin account is ready in the database.</li>'
        . '<li>Keep a copy of your <code>.env</code> in a safe place (never in Git).</li>'
        . '</ul><p><a class="btn primary" href="../">Visit your site</a></p></div>';
    inst_page_bottom();
    exit;
}

echo '<div class="card"><h2>1. Pre-flight checks</h2><table class="data"><tr><th>Check</th><th>Detail</th><th>Status</th></tr>';
foreach ($checks as $c) {
    echo '<tr><td>' . inst_h($c[0]) . '</td><td>' . inst_h($c[2]) . '</td><td class="' . ($c[1] ? 'ok' : 'bad') . '">'
        . ($c[1] ? 'PASS' : 'FAIL') . '</td></tr>';
}
echo '</table>';
if (!$canInstall) {
    echo '<p class="bad">Fix the failed checks above before installing.</p>';
}
echo '</div>';

if ($canInstall) {
    echo '<form method="post" action="">'
        . '<input type="hidden" name="action" value="install">'
        . '<input type="hidden" name="csrf_token" value="' . inst_h($csrf) . '">'
        . '<div class="card"><h2>2. Site and database</h2><div class="grid2">'
        . '<label>Site name<input name="site_name" value="' . $v('site_name', 'Oyejo Gas') . '" required></label>'
        . '<label>Site URL<input name="site_url" value="' . $v('site_url', 'http://localhost:8000') . '" required></label>'
        . '<label>DB host<input name="db_host" value="' . $v('db_host', '127.0.0.1') . '" required></label>'
        . '<label>DB port<input name="db_port" value="' . $v('db_port', '3306') . '" required></label>'
        . '<label>DB name<input name="db_name" value="' . $v('db_name', 'oyejo_gas') . '" required></label>'
        . '<label>DB username<input name="db_user" value="' . $v('db_user', 'root') . '" required></label>'
        . '</div><label>DB password<input type="password" name="db_pass" autocomplete="new-password"></label>'
        . '<p><label><input type="checkbox" name="db_create" value="1" checked> Create the database if it does not exist</label></p>'
        . '<p><label><input type="checkbox" name="debug" value="1"> Debug mode (show errors — local sites only)</label></p></div>'
        . '<div class="card"><h2>3. Super-admin account</h2><div class="grid2">'
        . '<label>Full name<input name="admin_name" value="' . $v('admin_name') . '" required></label>'
        . '<label>Email<input type="email" name="admin_email" value="' . $v('admin_email') . '" required></label>'
        . '<label>Phone (optional)<input name="admin_phone" value="' . $v('admin_phone') . '"></label>'
        . '<label>Password (min 8)<input type="password" name="admin_pass" autocomplete="new-password" required></label>'
        . '</div></div>'
        . '<p><button class="btn primary" type="submit">Install now</button></p></form>';
}
inst_page_bottom();
