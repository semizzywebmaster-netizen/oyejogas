#!/usr/bin/env php
<?php
/**
 * Oyejo Gas - project file checker (grows every phase).
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
    'about.php', 'contact.php', 'faq.php', 'terms.php', 'privacy.php',
    'shop.php', 'product.php',
    'config/config.php', 'config/database.php', 'config/.htaccess',
    'includes/bootstrap.php', 'includes/functions.php', 'includes/auth.php',
    'includes/mailer.php', 'includes/rbac.php', 'includes/header.php',
    'includes/catalog.php', 'includes/footer.php',
    'admin/index.php',
    'customer/index.php', 'customer/register.php', 'customer/login.php',
    'customer/logout.php', 'customer/forgot-password.php',
    'customer/reset-password.php', 'customer/verify-email.php',
    'customer/verify-phone.php',
    'driver/index.php',
    'api/index.php', 'install/index.php', 'install/installer.php',
    'errors/404.php', 'errors/403.php', 'errors/500.php',
    'assets/css/style.css', 'assets/js/app.js', 'assets/images/logo.svg',
    'assets/images/product-placeholder.svg',
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
    'tests/foundation-check.php', 'tests/index.php', 'tests/phase3-verify.sql',
    'docs/00-PROJECT-PLAN-28-PHASES.md',
    'docs/01-MASTER-REQUIREMENTS-CHECKLIST.md',
    'docs/01-Phase-1-Verification-Report.md',
    'docs/02-FOUNDATION-STRUCTURE.md',
    'docs/02-Phase-2-Verification-Report.md',
    'docs/03-DATABASE-DICTIONARY.md',
    'docs/03-Phase-3-Verification-Report.md',
    'docs/04-INSTALLATION-GUIDE.md',
    'docs/04-Phase-4-Verification-Report.md',
    'docs/05-Phase-5-Verification-Report.md',
    'docs/06-Phase-6-Verification-Report.md',
    'docs/07-Phase-7-Verification-Report.md',
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
check(strpos((string) @file_get_contents($root . '/config/config.php'), 'APP_KEY') !== false, 'config.php: APP_KEY signing secret');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'csrf_token') !== false, 'bootstrap.php: CSRF helpers');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'oyejo_feature') !== false, 'bootstrap.php: feature toggles');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'install.lock') !== false, 'bootstrap.php: installer redirect');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'auth_tick') !== false, 'bootstrap.php: session tick');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), '/rbac.php') !== false, 'bootstrap.php: loads RBAC');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'password_verify') !== false, 'auth.php: password_verify login');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'safe_next') !== false, 'auth.php: safe redirects');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'AUTH_IDLE_TIMEOUT') !== false, 'auth.php: session timeouts');
check(strpos((string) @file_get_contents($root . '/includes/rbac.php'), 'rbac_my_permissions') !== false, 'rbac.php: permission loader');
check(strpos((string) @file_get_contents($root . '/includes/rbac.php'), 'access.denied') !== false, 'rbac.php: denial logging');
check(strpos((string) @file_get_contents($root . '/includes/mailer.php'), 'mail.log') !== false, 'mailer.php: logged mailbox');
check(strpos((string) @file_get_contents($root . '/contact.php'), 'company') !== false, 'contact.php: honeypot field');
check(strpos((string) @file_get_contents($root . '/faq.php'), 'FROM `faqs`') !== false, 'faq.php: reads faqs table');
check(strpos((string) @file_get_contents($root . '/shop.php'), 'LIKE ? ESCAPE') !== false, 'shop.php: escaped search filter');
check(strpos((string) @file_get_contents($root . '/product.php'), '`p`.`slug` = ?') !== false, 'product.php: slug lookup');
check(strpos((string) @file_get_contents($root . '/index.php'), 'promo_now') !== false, 'index.php: featured promo window');
check(strpos((string) @file_get_contents($root . '/includes/catalog.php'), 'oyejo_promo_sql') !== false, 'catalog.php: promo-window SQL');
check(strpos((string) @file_get_contents($root . '/includes/catalog.php'), 'oyejo_stock_badge') !== false, 'catalog.php: stock badges');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'shop.php') !== false, 'header.php: shop nav link');
check(strpos((string) @file_get_contents($root . '/includes/footer.php'), 'terms.php') !== false, 'footer.php: legal links');
check(strpos((string) @file_get_contents($root . '/.env.example'), 'WHATSAPP_API_KEY') !== false, '.env.example: whatsapp keys');
check(strpos((string) @file_get_contents($root . '/.env.example'), 'APP_KEY') !== false, '.env.example: APP_KEY template');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.hero') !== false, 'style.css: hero styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '@media (max-width: 420px)') !== false, 'style.css: phone breakpoint');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.product-detail') !== false, 'style.css: shop styles');
check(strpos((string) @file_get_contents($root . '/assets/js/app.js'), 'window.Oyejo') !== false, 'app.js: Oyejo helper');
check(strpos((string) @file_get_contents($root . '/index.php'), 'reject_path_info') !== false, 'index.php: PATH_INFO 404 guard');
check(strpos((string) @file_get_contents($root . '/install/installer.php'), 'inst_split_sql') !== false, 'installer.php: DELIMITER-aware splitter');
check(strpos((string) @file_get_contents($root . '/install/installer.php'), 'install.lock') !== false, 'installer.php: reinstall lock');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'super_admin') !== false, 'seeds.sql: roles seeded');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'feature_toggles') !== false, 'seeds.sql: toggles seeded');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'RFL-125') !== false, 'seeds.sql: demo products');
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
