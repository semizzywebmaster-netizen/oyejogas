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
    'shop.php', 'product.php', 'cart.php', 'checkout.php',
    'blog.php', 'post.php', 'newsletter.php',
    'manifest.webmanifest', 'service-worker.js', 'offline.php',
    'config/config.php', 'config/database.php', 'config/.htaccess',
    'includes/bootstrap.php', 'includes/functions.php', 'includes/auth.php',
    'includes/mailer.php', 'includes/rbac.php', 'includes/header.php',
    'includes/catalog.php', 'includes/cart.php', 'includes/notify.php',
    'includes/wallet.php', 'includes/refills.php', 'includes/pickups.php',
    'includes/inventory.php', 'includes/admin.php', 'includes/delivery.php',
    'includes/payments.php', 'includes/support.php', 'includes/marketing.php',
    'includes/spin.php', 'includes/referrals.php', 'includes/ops.php', 'includes/errors.php',
    'includes/addons.php', 'includes/push.php', 'includes/daily.php', 'includes/wishlist.php', 'includes/sidebar.php',
    'includes/footer.php',
    'admin/index.php', 'admin/wallet.php', 'admin/refills.php',
    'admin/pickups.php', 'admin/inventory.php', 'admin/purchases.php',
    'admin/customers.php', 'admin/staff.php', 'admin/drivers.php',
    'admin/roles.php', 'admin/products.php', 'admin/orders.php',
    'admin/coupons.php', 'admin/settings.php', 'admin/addons.php',
    'admin/dispatch.php', 'admin/finance.php', 'admin/tickets.php',
    'admin/reviews.php', 'admin/marketing.php', 'admin/faqs.php',
    'admin/posts.php', 'admin/newsletter.php', 'admin/spin.php',
    'admin/referrals.php', 'admin/notifications.php', 'admin/backups.php', 'admin/logs.php',
    'admin/addon.php', 'admin/appearance.php', 'admin/pwa.php', 'admin/locations.php', 'admin/daily.php',
    'customer/index.php', 'customer/register.php', 'customer/login.php',
    'customer/logout.php', 'customer/forgot-password.php',
    'customer/reset-password.php', 'customer/verify-email.php',
    'customer/verify-phone.php', 'customer/orders.php', 'customer/profile.php',
    'customer/addresses.php', 'customer/phones.php', 'customer/security.php',
    'customer/notifications.php', 'customer/invoice.php', 'customer/reorder.php',
    'customer/tickets.php', 'customer/wallet.php', 'customer/statement.php',
    'customer/refills.php', 'customer/pickups.php', 'customer/payments.php',
    'customer/reviews.php', 'customer/spin.php', 'customer/referrals.php', 'customer/daily.php',
    'customer/wishlist.php',
    'driver/index.php',
    'api/index.php', 'api/payments-callback.php', 'api/push-subscribe.php',
    'install/index.php', 'install/installer.php',
    'errors/404.php', 'errors/403.php', 'errors/500.php', 'errors/503.php', 'errors/419.php', 'errors/429.php',
    'assets/css/style.css', 'assets/js/app.js', 'assets/images/logo.svg',
    'assets/images/product-placeholder.svg',
    'assets/images/.gitkeep', 'assets/icons/.gitkeep',
    'assets/icons/icon-192.png', 'assets/icons/icon-512.png',
    'assets/icons/maskable-192.png', 'assets/icons/maskable-512.png',
    'assets/.htaccess',
    'uploads/index.php', 'uploads/.htaccess', 'uploads/.gitkeep',
    'uploads/branding/index.php', 'uploads/branding/.gitkeep',
    'uploads/banners/index.php', 'uploads/banners/.gitkeep',
    'database/schema.sql', 'database/seeds.sql', 'database/.htaccess',
    'database/index.php', 'database/migrations/README.md',
    'database/migrations/.gitkeep',
    'database/migrations/20260911153000_wishlists.sql',
    'storage/.htaccess',
    'storage/logs/index.php', 'storage/logs/.gitkeep',
    'storage/backups/index.php', 'storage/backups/.gitkeep',
    'storage/cache/index.php', 'storage/cache/.gitkeep',
    'addons/README.md', 'addons/.gitkeep', 'addons/index.php', 'addons/.htaccess',
    'addons/example/addon.json', 'addons/example/README.md',
    'addons/example/pages/hello.php', 'addons/example/migrations/001-example-notes.sql',
    'addons/loyalty-points/addon.json', 'addons/gas-subscriptions/addon.json',
    'addons/corporate-accounts/addon.json', 'addons/multi-branch/addon.json',
    'addons/franchise/addon.json', 'addons/accounting/addon.json',
    'addons/whatsapp-automation/addon.json', 'addons/route-optimization/addon.json',
    'addons/wallet-withdrawals/addon.json', 'addons/multi-language/addon.json',
    'addons/multi-currency/addon.json',
    'cron/README.md', 'cron/.gitkeep', 'cron/index.php',
    'cron/send-notifications.php', 'cron/pickup-reminders.php', 'cron/backup.php', 'cron/rotate-logs.php',
    'cron/daily-reminders.php',
    'pwa/README.md', 'pwa/.gitkeep',
    'tests/foundation-check.php', 'tests/index.php', 'tests/phase3-verify.sql',
    'tests/daily-logic.php',
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
    'docs/08-Phase-8-Verification-Report.md',
    'docs/09-Phase-9-Verification-Report.md',
    'docs/10-Phase-10-Verification-Report.md',
    'docs/11-Phase-11-Verification-Report.md',
    'docs/12-Phase-12-Verification-Report.md',
    'docs/13-Phase-13-Verification-Report.md',
    'docs/14-Phase-14-Verification-Report.md',
    'docs/15-Phase-15-Verification-Report.md',
    'docs/16-Phase-16-Verification-Report.md',
    'docs/17-Phase-17-Verification-Report.md',
    'docs/18-Phase-18-Verification-Report.md',
    'docs/19-Phase-19-Verification-Report.md',
    'docs/20-Phase-20-Verification-Report.md',
    'docs/21-Phase-21-Verification-Report.md',
    'docs/22-Phase-22-Verification-Report.md',
    'docs/23-Phase-23-Verification-Report.md',
    'docs/24-Phase-24-Verification-Report.md',
    'docs/25-Phase-25-Verification-Report.md',
    'docs/26-Phase-26-Verification-Report.md',
    'docs/27-Phase-27-Verification-Report.md',
    'docs/28-Phase-28-Verification-Report.md',
    'docs/05-CPANEL-DEPLOYMENT.md',
    'docs/06-BACKUP-RESTORE-GUIDE.md',
    'docs/07-ADMINISTRATOR-GUIDE.md',
    'docs/ADDON-DEVELOPMENT.md',
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
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), '/notify.php') !== false, 'bootstrap.php: loads notify');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'password_verify') !== false, 'auth.php: password_verify login');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'safe_next') !== false, 'auth.php: safe redirects');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'AUTH_IDLE_TIMEOUT') !== false, 'auth.php: session timeouts');
check(strpos((string) @file_get_contents($root . '/includes/auth.php'), 'INSERT INTO `wallets`') !== false, 'auth.php: wallet auto-create');
check(strpos((string) @file_get_contents($root . '/includes/rbac.php'), 'rbac_my_permissions') !== false, 'rbac.php: permission loader');
check(strpos((string) @file_get_contents($root . '/includes/rbac.php'), 'access.denied') !== false, 'rbac.php: denial logging');
check(strpos((string) @file_get_contents($root . '/includes/mailer.php'), 'mail.log') !== false, 'mailer.php: logged mailbox');
check(strpos((string) @file_get_contents($root . '/contact.php'), 'company') !== false, 'contact.php: honeypot field');
check(strpos((string) @file_get_contents($root . '/faq.php'), 'FROM `faqs`') !== false, 'faq.php: reads faqs table');
check(strpos((string) @file_get_contents($root . '/shop.php'), 'LIKE ? ESCAPE') !== false, 'shop.php: escaped search filter');
check(strpos((string) @file_get_contents($root . '/product.php'), '`p`.`slug` = ?') !== false, 'product.php: slug lookup');
check(strpos((string) @file_get_contents($root . '/product.php'), 'value="add"') !== false, 'product.php: add-to-cart form');
check(strpos((string) @file_get_contents($root . '/index.php'), 'promo_now') !== false, 'index.php: featured promo window');
check(strpos((string) @file_get_contents($root . '/includes/catalog.php'), 'oyejo_promo_sql') !== false, 'catalog.php: promo-window SQL');
check(strpos((string) @file_get_contents($root . '/includes/catalog.php'), 'oyejo_stock_badge') !== false, 'catalog.php: stock badges');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'cart_place_order') !== false, 'cart engine: place-order');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'FOR UPDATE') !== false, 'cart engine: locked stock/wallet');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'uq_orders_number') !== false, 'cart engine: order-number retry');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'order_cancel') !== false, 'cart engine: cancellation rules');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'pay_record_checkout') !== false, 'cart engine: finance hook');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_emit') !== false, 'notify.php: emitter');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_request_topup') !== false, 'wallet lib: top-up requests');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_decide_topup') !== false, 'wallet lib: top-up decisions');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_credit') !== false, 'wallet lib: credit primitive');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_adjust') !== false, 'wallet lib: adjustments');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_reverse') !== false, 'wallet lib: reversals');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_freeze') !== false, 'wallet lib: freeze control');
check(strpos((string) @file_get_contents($root . '/includes/wallet.php'), 'wallet_audit') !== false, 'wallet lib: audit trail');
check(strpos((string) @file_get_contents($root . '/includes/refills.php'), 'refill_request') !== false, 'refill lib: requests');
check(strpos((string) @file_get_contents($root . '/includes/refills.php'), 'refill_set_status') !== false, 'refill lib: lifecycle');
check(strpos((string) @file_get_contents($root . '/includes/refills.php'), 'refill_flow') !== false, 'refill lib: flow map');
check(strpos((string) @file_get_contents($root . '/includes/pickups.php'), 'pickup_request') !== false, 'pickup lib: requests');
check(strpos((string) @file_get_contents($root . '/includes/pickups.php'), 'pickup_collect') !== false, 'pickup lib: collection');
check(strpos((string) @file_get_contents($root . '/includes/pickups.php'), 'pickup_serial_ok') !== false, 'pickup lib: serial validation');
check(strpos((string) @file_get_contents($root . '/includes/inventory.php'), 'inv_adjust') !== false, 'inventory lib: adjustments');
check(strpos((string) @file_get_contents($root . '/includes/inventory.php'), 'inv_move') !== false, 'inventory lib: movements');
check(strpos((string) @file_get_contents($root . '/includes/inventory.php'), 'inv_purchase_receive') !== false, 'inventory lib: purchase receiving');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'adm_staff_save') !== false, 'admin lib: staff');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'adm_role_save') !== false, 'admin lib: roles');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'adm_order_status') !== false, 'admin lib: order lifecycle');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'adm_settings_save') !== false, 'admin lib: settings');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'adm_toggle_set') !== false, 'admin lib: toggles');
check(strpos((string) @file_get_contents($root . '/includes/delivery.php'), 'del_assign') !== false, 'delivery lib: assignments');
check(strpos((string) @file_get_contents($root . '/includes/delivery.php'), 'del_collect_cash') !== false, 'delivery lib: cash collection');
check(strpos((string) @file_get_contents($root . '/includes/delivery.php'), 'del_pod_photo') !== false, 'delivery lib: POD photos');
check(strpos((string) @file_get_contents($root . '/includes/payments.php'), 'pay_verify') !== false, 'payments lib: verification');
check(strpos((string) @file_get_contents($root . '/includes/payments.php'), 'ref_decide') !== false, 'payments lib: refund decisions');
check(strpos((string) @file_get_contents($root . '/includes/payments.php'), 'recon_mark') !== false, 'payments lib: reconciliation');
check(strpos((string) @file_get_contents($root . '/includes/payments.php'), 'pay_cod_autoverify') !== false, 'payments lib: COD autoverify');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'sup_create') !== false, 'support lib: ticket creation');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'sup_reply_staff') !== false, 'support lib: staff replies');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'sup_close_customer') !== false, 'support lib: customer close');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'rev_submit') !== false, 'support lib: review submit');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'rev_moderate') !== false, 'support lib: review moderation');
check(strpos((string) @file_get_contents($root . '/includes/support.php'), 'delivered') !== false, 'support lib: verified-purchase gate');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), "'complaint','refund'") !== false, 'schema: complaint/refund categories');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'guest_email') !== false, 'schema: guest tickets');
check(strpos((string) @file_get_contents($root . '/admin/tickets.php'), 'tickets.manage') !== false, 'tickets desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/tickets.php'), 'finance.php?order_id=') !== false, 'tickets desk: finance handoff');
check(strpos((string) @file_get_contents($root . '/admin/reviews.php'), 'reviews.moderate') !== false, 'reviews desk: moderate permission');
check(strpos((string) @file_get_contents($root . '/customer/reviews.php'), 'rev_submit') !== false, 'reviews page: submit form');
check(strpos((string) @file_get_contents($root . '/customer/tickets.php'), 'sup_close_customer') !== false, 'tickets page: customer close');
check(strpos((string) @file_get_contents($root . '/contact.php'), 'sup_create') !== false, 'contact.php: ticket creation');
check(strpos((string) @file_get_contents($root . '/product.php'), 'rev_product_summary') !== false, 'product.php: review summary');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/reviews.php') !== false, 'dashboard: reviews link');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_banner_save') !== false, 'marketing lib: banners');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_campaign_save') !== false, 'marketing lib: campaigns');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_announce_save') !== false, 'marketing lib: announcements');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_faq_save') !== false, 'marketing lib: FAQs');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_post_save') !== false, 'marketing lib: posts');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_section_save') !== false, 'marketing lib: homepage sections');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_subscribe') !== false, 'marketing lib: newsletter');
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'mk_reports') !== false, 'marketing lib: reports');
check(strpos((string) @file_get_contents($root . '/admin/marketing.php'), 'marketing.campaigns') !== false, 'marketing desk: campaign permission');
check(strpos((string) @file_get_contents($root . '/admin/marketing.php'), 'marketing.banners') !== false, 'marketing desk: banner permission');
check(strpos((string) @file_get_contents($root . '/admin/faqs.php'), 'marketing.faqs') !== false, 'faqs desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/posts.php'), 'marketing.posts') !== false, 'posts desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/newsletter.php'), 'marketing.newsletter') !== false, 'newsletter desk: manage permission');
check(strpos((string) @file_get_contents($root . '/blog.php'), 'mk_posts_published') !== false, 'blog.php: published list');
check(strpos((string) @file_get_contents($root . '/post.php'), 'mk_post_by_slug') !== false, 'post.php: published single');
check(strpos((string) @file_get_contents($root . '/newsletter.php'), 'mk_subscribe') !== false, 'newsletter.php: subscribe form');
check(strpos((string) @file_get_contents($root . '/index.php'), 'mk_banners_live') !== false, 'index.php: live banners');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'spin_play') !== false, 'spin lib: play');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'random_int') !== false, 'spin lib: secure random');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'spin_eligibility') !== false, 'spin lib: eligibility');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'spin_reverse') !== false, 'spin lib: reversal');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'spin_claim') !== false, 'spin lib: claim');
check(strpos((string) @file_get_contents($root . '/includes/spin.php'), 'spin_expire_due') !== false, 'spin lib: expiry');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'reward_code') !== false, 'schema: spin reward codes');
check(strpos((string) @file_get_contents($root . '/admin/spin.php'), 'spin.manage') !== false, 'spin desk: manage permission');
check(strpos((string) @file_get_contents($root . '/customer/spin.php'), 'spin_to_win') !== false, 'spin page: toggle gate');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/spin.php') !== false, 'dashboard: spin link');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_capture') !== false, 'referral lib: capture');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_qualify_due') !== false, 'referral lib: qualification');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_claim') !== false, 'referral lib: claim');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_reverse') !== false, 'referral lib: reversal');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_flag') !== false, 'referral lib: flags');
check(strpos((string) @file_get_contents($root . '/includes/referrals.php'), 'ref_settings') !== false, 'referral lib: settings');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'flag_note') !== false, 'schema: referral flag notes');
check(strpos((string) @file_get_contents($root . '/customer/register.php'), 'ref_capture') !== false, 'register.php: referral capture');
check(strpos((string) @file_get_contents($root . '/admin/referrals.php'), 'referrals.manage') !== false, 'referrals desk: manage permission');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/referrals.php') !== false, 'dashboard: referrals link');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_process_queue') !== false, 'notify lib: queue worker');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_send') !== false, 'notify lib: dispatcher');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_config') !== false, 'notify lib: channel config');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_render') !== false, 'notify lib: templates');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_retry') !== false, 'notify lib: retries');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_promo') !== false, 'notify lib: promo blast');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_pickup_reminders') !== false, 'notify lib: pickup reminders');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), 'notify_wa_set') !== false, 'notify lib: wa opt-in');
check(strpos((string) @file_get_contents($root . '/includes/mailer.php'), 'whatsapp_send') !== false, 'mailer: whatsapp sender');
check(strpos((string) @file_get_contents($root . '/admin/notifications.php'), 'notifications.send') !== false, 'notif desk: send permission');
check(strpos((string) @file_get_contents($root . '/cron/send-notifications.php'), 'notify_process_queue') !== false, 'cron: queue worker');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'oyejo_maintenance_gate') !== false, 'maintenance: gate in bootstrap');
check(strpos((string) @file_get_contents($root . '/errors/503.php'), 'Retry-After') !== false, 'maintenance: 503 page');
check(strpos((string) @file_get_contents($root . '/includes/functions.php'), 'function setting(') !== false, 'settings: runtime reader');
check(strpos((string) @file_get_contents($root . '/includes/functions.php'), 'currency_symbol') !== false, 'settings: currency symbol wired');
check(strpos((string) @file_get_contents($root . '/includes/cart.php'), 'orders_prefix') !== false, 'settings: order prefix wired');
check(strpos((string) @file_get_contents($root . '/includes/mailer.php'), 'notif_from_email') !== false, 'settings: mail from fallback');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), "setting('site_name'") !== false, 'settings: site name in header');
check(strpos((string) @file_get_contents($root . '/index.php'), 'marketing_campaigns') !== false, 'toggles: marketing gated');
check(strpos((string) @file_get_contents($root . '/admin/addons.php'), "oyejo_feature('addons')") !== false, 'toggles: addons gated');
check(strpos((string) @file_get_contents($root . '/includes/notify.php'), "'push' => 'push_notifications'") !== false, 'toggles: push wired');
check(strpos((string) @file_get_contents($root . '/includes/ops.php'), 'ops_backup_run') !== false, 'ops lib: backup runner');
check(strpos((string) @file_get_contents($root . '/includes/ops.php'), 'ops_restore') !== false, 'ops lib: restore');
check(strpos((string) @file_get_contents($root . '/includes/ops.php'), 'ops_health') !== false, 'ops lib: health checks');
check(strpos((string) @file_get_contents($root . '/includes/ops.php'), 'ops_cleanup') !== false, 'ops lib: cleanup');
check(strpos((string) @file_get_contents($root . '/includes/ops.php'), 'ops_backup_download') !== false, 'ops lib: secure download');
check(strpos((string) @file_get_contents($root . '/admin/backups.php'), 'backups.restore') !== false, 'backups desk: restore permission');
check(strpos((string) @file_get_contents($root . '/cron/backup.php'), 'ops_backup_run') !== false, 'cron: scheduled backup');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `backups`') !== false, 'schema: backups table');
check(strpos((string) @file_get_contents($root . '/includes/errors.php'), 'report_error') !== false, 'errors lib: reporter');
check(strpos((string) @file_get_contents($root . '/includes/errors.php'), 'show_error') !== false, 'errors lib: safe pages');
check(strpos((string) @file_get_contents($root . '/includes/errors.php'), 'rate_limit') !== false, 'errors lib: rate limiter');
check(strpos((string) @file_get_contents($root . '/includes/errors.php'), 'logs_rotate') !== false, 'errors lib: rotation');
check(strpos((string) @file_get_contents($root . '/includes/errors.php'), 'err_resolve') !== false, 'errors lib: resolve tracking');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'register_shutdown_function') !== false, 'bootstrap: fatal capture');
check(strpos((string) @file_get_contents($root . '/customer/reset-password.php'), 'err_419') !== false, 'reset: 419 on expired link');
check(strpos((string) @file_get_contents($root . '/contact.php'), 'err_429') !== false, 'contact: 429 throttle');
check(strpos((string) @file_get_contents($root . '/admin/logs.php'), 'logs.manage') !== false, 'logs desk: manage permission');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `error_reports`') !== false, 'schema: error reports');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'OYEJO_VERSION') !== false, 'bootstrap: platform version');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_install') !== false, 'addons lib: installer');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_migrate') !== false, 'addons lib: migrations');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_check_manifest') !== false, 'addons lib: dependency checks');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_set_status') !== false, 'addons lib: enable/disable');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_page_file') !== false, 'addons lib: page resolver');
check(strpos((string) @file_get_contents($root . '/includes/addons.php'), 'function addon_menus') !== false, 'addons lib: console menus');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), 'addon_setting_groups') !== false, 'settings: add-on groups merge');
check(strpos((string) @file_get_contents($root . '/admin/addon.php'), 'addon_page_file') !== false, 'addon router: manifest gating');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `addon_migrations`') !== false, 'schema: addon migrations');
check(strpos((string) @file_get_contents($root . '/.htaccess'), 'RedirectMatch 403 ^/addons') !== false, 'htaccess: addons blocked');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'manifest.webmanifest') !== false, 'header: PWA manifest link');
check(strpos((string) @file_get_contents($root . '/assets/js/app.js'), 'serviceWorker') !== false, 'app.js: SW registration');
check(strpos((string) @file_get_contents($root . '/service-worker.js'), 'admin|customer|driver|api|install|cron') !== false, 'SW: private areas bypass cache');
check(strpos((string) @file_get_contents($root . '/service-worker.js'), 'showNotification') !== false, 'SW: push display');
check(strpos((string) @file_get_contents($root . '/includes/push.php'), 'function push_encrypt') !== false, 'push lib: payload encryption');
check(strpos((string) @file_get_contents($root . '/api/push-subscribe.php'), 'csrf_verify') !== false, 'push api: CSRF gate');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `push_subscriptions`') !== false, 'schema: push subscriptions');
check(strpos((string) @file_get_contents($root . '/includes/functions.php'), '?v=') !== false, 'assets: versioned URLs');
$sw_ver = '';
if (preg_match("/VERSION = '([0-9.]+)'/", (string) @file_get_contents($root . '/service-worker.js'), $mm)) {
    $sw_ver = $mm[1];
}
$boot_ver = '';
if (preg_match("/OYEJO_VERSION', '([0-9.]+)'/", (string) @file_get_contents($root . '/includes/bootstrap.php'), $mm2)) {
    $boot_ver = $mm2[1];
}
check($sw_ver !== '' && $sw_ver === $boot_ver, 'SW: cache version matches platform (' . $sw_ver . ')');
check(strpos((string) @file_get_contents($root . '/checkout.php'), 'guest_checkout') !== false, 'checkout.php: guest toggle gate');
check(strpos((string) @file_get_contents($root . '/checkout.php'), 'shop.order') !== false, 'checkout.php: order permission');
check(strpos((string) @file_get_contents($root . '/checkout.php'), 'notify_emit') !== false, 'checkout.php: confirmation notify');
check(strpos((string) @file_get_contents($root . '/checkout.php'), 'customer/payments.php') !== false, 'checkout.php: payments redirect');
check(strpos((string) @file_get_contents($root . '/customer/orders.php'), 'cancelled_reason') !== false, 'orders.php: cancellation UI');
check(strpos((string) @file_get_contents($root . '/customer/orders.php'), 'reorder.php') !== false, 'orders.php: reorder link');
check(strpos((string) @file_get_contents($root . '/customer/orders.php'), 'Tracking') !== false, 'orders.php: tracking timeline');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'My referral link') !== false, 'dashboard: referral section');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/wallet.php') !== false, 'dashboard: wallet link');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/refills.php') !== false, 'dashboard: refill link');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/pickups.php') !== false, 'dashboard: pickup link');
check(strpos((string) @file_get_contents($root . '/customer/profile.php'), 'phone_verified_at') !== false, 'profile.php: verification reset');
check(strpos((string) @file_get_contents($root . '/customer/addresses.php'), 'is_default') !== false, 'addresses.php: default handling');
check(strpos((string) @file_get_contents($root . '/customer/phones.php'), 'customer_phones') !== false, 'phones.php: phone book');
check(strpos((string) @file_get_contents($root . '/customer/security.php'), 'login_attempts') !== false, 'security.php: login history');
check(strpos((string) @file_get_contents($root . '/customer/notifications.php'), 'notify_for_customer') !== false, 'notifications.php: inbox reader');
check(strpos((string) @file_get_contents($root . '/customer/invoice.php'), 'pay_invoice_for_order') !== false, 'invoice.php: persistent invoice');
check(strpos((string) @file_get_contents($root . '/customer/invoice.php'), 'Receipt') !== false, 'invoice.php: receipt mode');
check(strpos((string) @file_get_contents($root . '/customer/reorder.php'), 'cart_add') !== false, 'reorder.php: re-add lines');
check(strpos((string) @file_get_contents($root . '/customer/tickets.php'), 'sup_reply_customer') !== false, 'tickets.php: replies');
check(strpos((string) @file_get_contents($root . '/customer/tickets.php'), 'tickets.own') !== false, 'tickets.php: own-tickets permission');
check(strpos((string) @file_get_contents($root . '/customer/wallet.php'), 'wallet_request_topup') !== false, 'wallet page: top-up form');
check(strpos((string) @file_get_contents($root . '/customer/statement.php'), 'Opening balance') !== false, 'statement.php: derived statement');
check(strpos((string) @file_get_contents($root . '/customer/refills.php'), 'gas_refills') !== false, 'refills page: toggle gate');
check(strpos((string) @file_get_contents($root . '/customer/refills.php'), 'refill_request') !== false, 'refills page: booking form');
check(strpos((string) @file_get_contents($root . '/customer/pickups.php'), 'cylinder_pickups') !== false, 'pickups page: toggle gate');
check(strpos((string) @file_get_contents($root . '/customer/pickups.php'), 'cylinders_held') !== false, 'pickups page: held cylinders');
check(strpos((string) @file_get_contents($root . '/customer/payments.php'), 'pay_upload_proof') !== false, 'payments page: proof upload');
check(strpos((string) @file_get_contents($root . '/admin/wallet.php'), 'payments.verify') !== false, 'wallet desk: approval permission');
check(strpos((string) @file_get_contents($root . '/admin/wallet.php'), 'wallet.adjust') !== false, 'wallet desk: adjustment permission');
check(strpos((string) @file_get_contents($root . '/admin/refills.php'), 'refills.manage') !== false, 'refill desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/pickups.php'), 'pickups.manage') !== false, 'pickup desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/inventory.php'), 'inventory.adjust') !== false, 'inventory desk: adjust permission');
check(strpos((string) @file_get_contents($root . '/admin/inventory.php'), 'cylinders.manage') !== false, 'inventory desk: cylinder permission');
check(strpos((string) @file_get_contents($root . '/admin/purchases.php'), 'purchases.manage') !== false, 'purchases desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/purchases.php'), 'suppliers.manage') !== false, 'purchases desk: supplier permission');
check(strpos((string) @file_get_contents($root . '/admin/customers.php'), 'customers.suspend') !== false, 'customers desk: suspend permission');
check(strpos((string) @file_get_contents($root . '/admin/staff.php'), 'users.create') !== false, 'staff desk: create permission');
check(strpos((string) @file_get_contents($root . '/admin/roles.php'), 'roles.manage') !== false, 'roles desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/orders.php'), 'orders.cancel') !== false, 'orders desk: cancel permission');
check(strpos((string) @file_get_contents($root . '/admin/settings.php'), 'toggles.manage') !== false, 'settings desk: toggle permission');
check(strpos((string) @file_get_contents($root . '/admin/addons.php'), 'addons.manage') !== false, 'addons desk: manage permission');
check(strpos((string) @file_get_contents($root . '/admin/dispatch.php'), 'deliveries.assign') !== false, 'dispatch desk: assign permission');
check(strpos((string) @file_get_contents($root . '/admin/dispatch.php'), 'zones.manage') !== false, 'dispatch desk: zone permission');
check(strpos((string) @file_get_contents($root . '/admin/finance.php'), 'payments.verify') !== false, 'finance desk: verify permission');
check(strpos((string) @file_get_contents($root . '/admin/finance.php'), 'refunds.manage') !== false, 'finance desk: refund permission');
check(strpos((string) @file_get_contents($root . '/driver/index.php'), 'del_collect_cash') !== false, 'driver portal: cash action');
check(strpos((string) @file_get_contents($root . '/driver/index.php'), 'driver_portal') !== false, 'driver portal: toggle gate');
check(strpos((string) @file_get_contents($root . '/api/payments-callback.php'), 'hash_equals') !== false, 'callback: signed webhook');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'shop.php') !== false, 'header.php: shop nav link');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'oyejo_cart') !== false, 'header.php: cart link');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'customer/refills.php') !== false, 'header.php: refill link');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'customer/pickups.php') !== false, 'header.php: pickup link');
check(strpos((string) @file_get_contents($root . '/includes/footer.php'), 'terms.php') !== false, 'footer.php: legal links');
check(strpos((string) @file_get_contents($root . '/.env.example'), 'WHATSAPP_API_KEY') !== false, '.env.example: whatsapp keys');
check(strpos((string) @file_get_contents($root . '/.env.example'), 'APP_KEY') !== false, '.env.example: APP_KEY template');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.hero') !== false, 'style.css: hero styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '@media (max-width: 420px)') !== false, 'style.css: phone breakpoint');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.product-detail') !== false, 'style.css: shop styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.checkout-grid') !== false, 'style.css: checkout styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.dash-grid') !== false, 'style.css: dashboard styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.txn-credit') !== false, 'style.css: wallet styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.stat-grid') !== false, 'style.css: stat-grid styles');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.perm-group') !== false, 'style.css: perm-group styles');
check(strpos((string) @file_get_contents($root . '/assets/js/app.js'), 'window.Oyejo') !== false, 'app.js: Oyejo helper');
check(strpos((string) @file_get_contents($root . '/index.php'), 'reject_path_info') !== false, 'index.php: PATH_INFO 404 guard');
check(strpos((string) @file_get_contents($root . '/install/installer.php'), 'inst_split_sql') !== false, 'installer.php: DELIMITER-aware splitter');
check(strpos((string) @file_get_contents($root . '/install/installer.php'), 'install.lock') !== false, 'installer.php: reinstall lock');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'super_admin') !== false, 'seeds.sql: roles seeded');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'feature_toggles') !== false, 'seeds.sql: toggles seeded');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'RFL-125') !== false, 'seeds.sql: demo products');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'WELCOME10') !== false, 'seeds.sql: demo coupons');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'wallet_balance_cap_minor') !== false, 'seeds.sql: wallet limits');
$manifest = json_decode((string) @file_get_contents($root . '/addons/example/addon.json'), true);
check(is_array($manifest) && ($manifest['slug'] ?? '') === 'example', 'addon.json: valid manifest');

// --- Daily earn (check-in / streak / missions) ---
check(strpos((string) @file_get_contents($root . '/includes/daily.php'), 'function daily_checkin') !== false, 'daily lib: check-in');
check(strpos((string) @file_get_contents($root . '/includes/daily.php'), 'function daily_compute_reward') !== false, 'daily lib: server-side reward math');
check(strpos((string) @file_get_contents($root . '/includes/daily.php'), 'random_int') !== false, 'daily lib: secure mystery roll');
check(strpos((string) @file_get_contents($root . '/includes/daily.php'), 'UNIQUE') !== false || strpos((string) @file_get_contents($root . '/database/schema.sql'), 'uq_checkin_day') !== false, 'schema: one check-in per day');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `daily_checkins`') !== false, 'schema: daily_checkins');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `daily_mission_claims`') !== false, 'schema: daily_mission_claims');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'daily_rewards') !== false, 'seeds.sql: daily_rewards toggle');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), "'daily_rewards'") !== false, 'bootstrap: daily_rewards default');
check(strpos((string) @file_get_contents($root . '/customer/daily.php'), 'daily_enabled') !== false, 'daily page: toggle gate');
check(strpos((string) @file_get_contents($root . '/customer/daily.php'), 'csrf_field') !== false, 'daily page: CSRF');
check(strpos((string) @file_get_contents($root . '/admin/daily.php'), 'daily.manage') !== false, 'daily desk: manage permission');
check(strpos((string) @file_get_contents($root . '/includes/sidebar.php'), 'customer/daily.php') !== false, 'sidebar: daily earn');
check(strpos((string) @file_get_contents($root . '/includes/sidebar.php'), 'admin/daily.php') !== false, 'sidebar: daily desk');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/daily.php') !== false, 'dashboard: daily earn link');
check(strpos((string) @file_get_contents($root . '/customer/login.php'), 'daily_should_nudge') !== false, 'login: daily landing nudge');
check(strpos((string) @file_get_contents($root . '/shop.php'), "daily_mark_seen('shop')") !== false, 'shop: mission seen');
check(strpos((string) @file_get_contents($root . '/cron/daily-reminders.php'), 'daily_queue_reminders') !== false, 'cron: streak reminders');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), "'daily' =>") !== false, 'admin lib: daily settings group');
check(strpos((string) @file_get_contents($root . '/assets/css/style.css'), '.daily-week') !== false, 'style.css: daily earn styles');
check(strpos((string) @file_get_contents($root . '/install/installer.php'), "'26'") !== false, 'installer: 26 feature toggles');

// --- Wishlist (saved products) ---
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'function wish_add') !== false, 'wishlist lib: add');
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'function wish_remove') !== false, 'wishlist lib: remove');
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'function wish_clear') !== false, 'wishlist lib: clear');
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'function wish_button') !== false, 'wishlist lib: shop/product button');
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'WISH_MAX') !== false, 'wishlist lib: cap');
check(strpos((string) @file_get_contents($root . '/includes/wishlist.php'), 'rate_limit') !== false, 'wishlist lib: rate limit');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'CREATE TABLE IF NOT EXISTS `wishlists`') !== false, 'schema: wishlists');
check(strpos((string) @file_get_contents($root . '/database/schema.sql'), 'uq_wish_customer_product') !== false, 'schema: unique customer+product');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), '/wishlist.php') !== false, 'bootstrap: loads wishlist');
check(strpos((string) @file_get_contents($root . '/includes/bootstrap.php'), 'wish_ensure_schema') !== false, 'bootstrap: auto-schema');
check(strpos((string) @file_get_contents($root . '/customer/wishlist.php'), 'csrf_field') !== false, 'wishlist page: CSRF');
check(strpos((string) @file_get_contents($root . '/customer/wishlist.php'), 'require_login') !== false, 'wishlist page: login gate');
check(strpos((string) @file_get_contents($root . '/customer/wishlist.php'), 'to_cart_all') !== false, 'wishlist page: add all to cart');
check(strpos((string) @file_get_contents($root . '/includes/sidebar.php'), 'customer/wishlist.php') !== false, 'sidebar: wishlist');
check(strpos((string) @file_get_contents($root . '/includes/header.php'), 'customer/wishlist.php') !== false, 'header: wishlist count');
check(strpos((string) @file_get_contents($root . '/customer/index.php'), 'customer/wishlist.php') !== false, 'dashboard: wishlist link');
check(strpos((string) @file_get_contents($root . '/shop.php'), 'wish_button') !== false, 'shop: wish button');
check(strpos((string) @file_get_contents($root . '/product.php'), 'wish_button') !== false, 'product: wish button');

// --- Growth B2: banners, PWA desk, locations desk ---
check(strpos((string) @file_get_contents($root . '/includes/marketing.php'), 'function mk_banner_upload') !== false, 'marketing lib: banner upload helper');
check(strpos((string) @file_get_contents($root . '/admin/marketing.php'), 'banner_image') !== false, 'marketing desk: banner file input');
check(strpos((string) @file_get_contents($root . '/index.php'), 'url((string) $b[') !== false, 'homepage: banners use url()');
check(strpos((string) @file_get_contents($root . '/admin/pwa.php'), 'function pwa_regenerate') !== false, 'pwa desk: manifest regenerator');
check(strpos((string) @file_get_contents($root . '/includes/admin.php'), "'pwa' =>") !== false, 'admin lib: pwa settings group');
check(strpos((string) @file_get_contents($root . '/database/seeds.sql'), 'pwa_short_name') !== false, 'seeds.sql: PWA defaults');
check(strpos((string) @file_get_contents($root . '/includes/sidebar.php'), 'admin/pwa.php') !== false, 'sidebar: PWA desk');
check(strpos((string) @file_get_contents($root . '/includes/sidebar.php'), 'admin/locations.php') !== false, 'sidebar: locations desk');
check(strpos((string) @file_get_contents($root . '/admin/locations.php'), 'zones.manage') !== false, 'locations desk: zones gate');
$pwa = json_decode((string) @file_get_contents($root . '/manifest.webmanifest'), true);
check(is_array($pwa) && ($pwa['display'] ?? '') === 'standalone', 'manifest.webmanifest: valid PWA JSON');

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
