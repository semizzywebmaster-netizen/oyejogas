<?php
/**
 * Oyejo Gas - portal sidebar navigation (shared by header layout).
 *
 * Admin desks and customer account links live here so the admin console
 * cards and the sidebar can never drift apart. Items are filtered by
 * permission server-side; the sidebar only renders for logged-in users.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Admin desks: [label, path, permission]. Single source of truth. */
function sidebar_admin_desks() {
    return [
        ['Orders', 'admin/orders.php', 'orders.view'],
        ['Refills', 'admin/refills.php', 'refills.view'],
        ['Pickups', 'admin/pickups.php', 'pickups.view'],
        ['Dispatch', 'admin/dispatch.php', 'deliveries.view'],
        ['Locations', 'admin/locations.php', 'zones.manage'],
        ['Inventory', 'admin/inventory.php', 'inventory.view'],
        ['Purchases', 'admin/purchases.php', 'purchases.manage'],
        ['Wallet', 'admin/wallet.php', 'wallet.view'],
        ['Customers', 'admin/customers.php', 'customers.view'],
        ['Staff', 'admin/staff.php', 'users.view'],
        ['Drivers', 'admin/drivers.php', 'drivers.view'],
        ['Roles', 'admin/roles.php', 'roles.view'],
        ['Products', 'admin/products.php', 'products.view'],
        ['Coupons', 'admin/coupons.php', 'coupons.manage'],
        ['Finance', 'admin/finance.php', 'payments.view'],
        ['Support', 'admin/tickets.php', 'tickets.manage'],
        ['Reviews', 'admin/reviews.php', 'reviews.moderate'],
        ['Marketing', 'admin/marketing.php', 'marketing.campaigns'],
        ['FAQs', 'admin/faqs.php', 'marketing.faqs'],
        ['Posts', 'admin/posts.php', 'marketing.posts'],
        ['Newsletter', 'admin/newsletter.php', 'marketing.newsletter'],
        ['Spin-to-win', 'admin/spin.php', 'spin.manage'],
        ['Daily rewards', 'admin/daily.php', 'daily.manage'],
        ['Referrals', 'admin/referrals.php', 'referrals.manage'],
        ['Notifications', 'admin/notifications.php', 'notifications.view'],
        ['Backups', 'admin/backups.php', 'backups.create'],
        ['Error logs', 'admin/logs.php', 'logs.view'],
        ['Appearance', 'admin/appearance.php', 'settings.view'],
        ['PWA', 'admin/pwa.php', 'settings.view'],
        ['Settings', 'admin/settings.php', 'settings.view'],
        ['Add-ons', 'admin/addons.php', 'addons.view'],
    ];
}

/** Customer account links: [label, path]. Visibility is by login only. */
function sidebar_customer_links() {
    $links = [
        ['Dashboard', 'customer/'],
        ['Orders', 'customer/orders.php'],
        ['Wallet', 'customer/wallet.php'],
        ['Refills', 'customer/refills.php'],
        ['Pickups', 'customer/pickups.php'],
        ['Payments', 'customer/payments.php'],
        ['Referrals', 'customer/referrals.php'],
        ['Support tickets', 'customer/tickets.php'],
        ['Inbox', 'customer/notifications.php'],
    ];
    if (function_exists('oyejo_feature') && oyejo_feature('reviews')) {
        $links[] = ['My reviews', 'customer/reviews.php'];
    }
    if (function_exists('oyejo_feature') && oyejo_feature('spin_to_win')) {
        $links[] = ['Spin-to-win', 'customer/spin.php'];
    }
    if (function_exists('daily_enabled') && daily_enabled()) {
        array_splice($links, 3, 0, [['Daily earn', 'customer/daily.php']]);
    }
    $links[] = ['Profile', 'customer/profile.php'];
    $links[] = ['Addresses', 'customer/addresses.php'];
    $links[] = ['Phones', 'customer/phones.php'];
    $links[] = ['Security', 'customer/security.php'];
    return $links;
}

/**
 * Which portal sidebar (if any) applies to the current request.
 * Guest pages (login/register/forgot/reset/verify) never get one.
 */
function sidebar_portal() {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        return '';
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/admin/')) {
        return 'admin';
    }
    if (str_contains($script, '/customer/')) {
        $guest = ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php',
            'verify-email.php', 'verify-phone.php', 'logout.php'];
        foreach ($guest as $g) {
            if (str_ends_with($script, '/' . $g)) {
                return '';
            }
        }
        return 'customer';
    }
    return '';
}

/** Render the sidebar nav for a portal with active-state highlight. */
function sidebar_render($portal) {
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $items = $portal === 'admin' ? sidebar_admin_desks() : sidebar_customer_links();
    $home = $portal === 'admin' ? 'admin/' : 'customer/';
    $home_label = $portal === 'admin' ? 'Console' : 'Dashboard';
    echo '<aside class="side" aria-label="Portal navigation"><nav>';
    $active = str_ends_with($script, '/' . $home) || str_ends_with($script, $home . 'index.php')
        ? ' aria-current="page"' : '';
    // The Dashboard/Console card entry doubles as the home link.
    if ($portal === 'customer') {
        echo '<a class="side-home" href="' . e(url($home)) . '"' . $active . '>'
            . e($home_label) . '</a>';
    } else {
        echo '<a class="side-home" href="' . e(url($home)) . '"' . $active . '>'
            . e($home_label) . '</a>';
    }
    foreach ($items as $item) {
        [$label, $path] = $item;
        if ($portal === 'admin' && !has_permission($item[2])) {
            continue;
        }
        if ($portal === 'customer' && $path === 'customer/') {
            continue; // home link already rendered above
        }
        $is_active = str_ends_with($script, '/' . $path);
        echo '<a href="' . e(url($path)) . '"' . ($is_active ? ' aria-current="page"' : '') . '>'
            . e($label) . '</a>';
    }
    echo '</nav></aside>';
}
