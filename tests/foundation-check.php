#!/usr/bin/env php
<?php
/**
 * Oyejo Gas - Phase 2 foundation checker.
 * Usage: php tests/foundation-check.php   (exit 0 = all pass)
 * Checks: expected files exist, key protections present, php -l on all PHP.
 */
$root = dirname(__DIR__);
$fail = 0;
$pass = 0;

function check($cond, $label) {
    global $fail, $pass;
    if ($cond) {
        $pass++;
        echo "PASS  $label\n";
    } else {
        $fail++;
        echo "FAIL  $label\n";
    }
}

$expected = [
    '.htaccess', '.env.example', '.gitignore', 'index.php',
    'config/config.php', 'config/database.php', 'config/.htaccess',
    'includes/bootstrap.php', 'includes/functions.php',
    'includes/header.php', 'includes/footer.php',
    'admin/index.php', 'customer/index.php', 'driver/index.php',
    'api/index.php', 'install/index.php',
    'errors/404.php', 'errors/403.php', 'errors/500.php',
    'assets/css/style.css', 'assets/js/app.js', 'assets/images/logo.svg',
    'assets/images/.gitkeep', 'assets/icons/.gitkeep',
    'uploads/index.php', 'uploads/.htaccess', 'uploads/.gitkeep',
    'database/schema.sql', 'database/seeds.sql', 'database/.htaccess',
    'database/index.php', 'database/migrations/README.md',
    'database/migrations/.gitkeep',
    'storage/.htaccess',
    'storage/logs/index.php', 'storage/logs/.gitkeep',
    'storage/backups/index.php', 'storage/backups/.gitkeep',
    'storage/cache/index.php', 'storage/cache/.gitkeep',
    'addons/README.md', 'addons/.gitkeep', 'addons/index.php',
    'addons/_example/addon.json', 'addons/_example/README.md',
    'cron/README.md', 'cron/.gitkeep', 'cron/index.php',
    'pwa/README.md', 'pwa/.gitkeep',
    'tests/foundation-check.php', 'tests/index.php',
    'docs/00-PROJECT-PLAN-28-PHASES.md',
    'docs/01-MASTER-REQUIREMENTS-CHECKLIST.md',
    'README.md',
];
foreach ($expected as $f) {
    check(is_file($root . '/' . $f), $f);
}

// --- Key protection/content assertions ---
$rootHt = (string) @file_get_contents($root . '/.htaccess');
check(strpos($rootHt, 'ErrorDocument 404') !== false, '.htaccess: ErrorDocument 404');
check(strpos($rootHt, 'RedirectMatch 403') !== false, '.htaccess: folder blocks');
check(strpos($rootHt, 'X-Content-Type-Options') !== false, '.htaccess: security headers');
check(strpos((string) @file_get_contents($root . '/uploads/.htaccess'), 'php_flag engine off') !== false, 'uploads/.htaccess: php engine off');
check(strpos((string) @file_get_contents($root . '/storage/.htaccess'), 'Require all denied') !== false, 'storage/.htaccess: denied');
check(strpos((string) @file_get_contents($root . '/config/config.php'), 'OYEJO_BOOT') !== false, 'config.php: direct-access guard');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'csrf_token') !== false, 'bootstrap.php: CSRF helpers');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'oyejo_feature') !== false, 'bootstrap.php: feature toggles');
check(strpos((string) @file_get_contents($root . '/.env.example'), 'WHATSAPP_API_KEY') !== false, '.env.example: whatsapp keys');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.hero') !== false, 'style.css: hero styles');
check(strpos((string) @file_get_contents($root . '/assets/js/app.js'), 'window.Oyejo') !== false, 'app.js: Oyejo helper');
check(strpos((string) @file_get_contents($root . '/index.php'), 'reject_path_info') !== false, 'index.php: PATH_INFO 404 guard');
$manifest = json_decode((string) @file_get_contents($root . '/addons/_example/addon.json'), true);
check(is_array($manifest) && ($manifest['slug'] ?? '') === 'example', 'addon.json: valid manifest');

// --- php -l over every PHP file ---
$linted = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if ($file->isDir() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $linted++;
    $out = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        check(false, 'php -l ' . substr($file->getPathname(), strlen($root) + 1) . ' :: ' . implode(' ', $out));
    }
}
check($linted > 0, "php -l executed on $linted PHP files");

echo "\n==== $pass passed, $fail failed ====\n";
exit($fail > 0 ? 1 : 0);
