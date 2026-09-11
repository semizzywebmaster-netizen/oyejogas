<?php
/**
 * Oyejo Gas - shared page header (Phase 7 navigation).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$mainNav = [
    ['Home', url('')],
    ['Shop', url('shop.php')],
    ['Refill', url('customer/refills.php')],
    ['Pickup', url('customer/pickups.php')],
    ['About', url('about.php')],
    ['FAQ', url('faq.php')],
    ['Blog', url('blog.php')],
    ['Contact', url('contact.php')],
];
if (is_logged_in()) {
    $mainNav[] = ['My account', url('customer/')];
} else {
    $mainNav[] = ['Login', url('customer/login.php')];
    $mainNav[] = ['Register', url('customer/register.php')];
}
// Staff portals stay out of the public menu: drivers use the footer link,
// admins use the direct /admin/ URL. Keeps the header uncluttered.
$__logo = (string) setting('site_logo', '');
if ($__logo === '' || !str_starts_with($__logo, 'uploads/branding/')) {
    $__logo = 'assets/images/logo.svg';
}
$__favicon = (string) setting('site_favicon', '');
if ($__favicon === '' || !str_starts_with($__favicon, 'uploads/branding/')) {
    $__favicon = 'assets/images/logo.svg';
}
$__primary = (string) setting('site_color_primary', '#0b6b3a');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $__primary)) {
    $__primary = '#0b6b3a';
}
$__accent = (string) setting('site_color_accent', '#ff9d2e');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $__accent)) {
    $__accent = '#ff9d2e';
}
require_once BASE_PATH . '/includes/sidebar.php';
$GLOBALS['oyejo_sidebar'] = sidebar_portal();
$__logo_url = str_starts_with($__logo, 'assets/') ? asset($__logo) : url($__logo);
$__favicon_url = str_starts_with($__favicon, 'assets/') ? asset($__favicon) : url($__favicon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($page_title ?? setting('site_name', APP_NAME)) ?> — <?= e(setting('site_name', APP_NAME)) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<style>:root{--green:<?= e($__primary) ?>;--green-dark:<?= e($__primary) ?>;--orange:<?= e($__accent) ?>;}</style>
<script>(function(){try{if(localStorage.getItem('oyejo-theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
<link rel="icon" href="<?= e($__favicon_url) ?>" type="<?= str_ends_with($__favicon, '.svg') ? 'image/svg+xml' : 'image/png' ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e($__primary) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?= e(asset('assets/icons/icon-192.png')) ?>">
</head>
<body>
<?php if (oyejo_feature('maintenance_mode')) : ?>
<div class="maint" role="alert">Scheduled maintenance is in progress. Some services may be unavailable.</div>
<?php endif; ?>
<?php
$__daily_nudge = false;
$__script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (function_exists('daily_should_nudge') && !str_ends_with($__script, '/customer/daily.php')) {
    try {
        $__daily_nudge = daily_should_nudge();
    } catch (Throwable $t) {
        $__daily_nudge = false;
    }
}
?>
<?php if ($__daily_nudge) : ?>
<div class="maint daily-nudge" role="status">Your daily gas credit is waiting. <a href="<?= e(url('customer/daily.php')) ?>">Claim it now →</a></div>
<?php endif; ?>
<header class="site">
  <div class="wrap nav">
    <a class="brand" href="<?= e(url('')) ?>">
      <img src="<?= e($__logo_url) ?>" alt="" width="36" height="36">
      <span><?= e(setting('site_name', APP_NAME)) ?></span>
    </a>
    <button class="hamburger" id="navToggle" aria-label="Menu" aria-expanded="false">&#9776;</button>
    <nav class="links" id="navLinks">
      <?php foreach ($mainNav as $item) : ?>
        <a href="<?= e($item[1]) ?>"><?= e($item[0]) ?></a>
      <?php endforeach; ?>
      <?php $cartN = array_sum($_SESSION['oyejo_cart'] ?? []); if ($cartN > 0) : ?>
        <a href="<?= e(url('cart.php')) ?>">Cart (<?= (int) $cartN ?>)</a>
      <?php endif; ?>
      <?php if (is_logged_in()) : ?>
        <span class="who">Hi, <?= e(current_user()['name']) ?></span>
        <a href="<?= e(url('customer/logout.php')) ?>">Logout</a>
      <?php endif; ?>
      <button type="button" id="themeToggle" class="linklike" aria-label="Toggle day/night mode">&#9788;</button>
    </nav>
  </div>
</header>
<main class="wrap">
<?php if ($GLOBALS['oyejo_sidebar'] !== '') : ?><div class="layout"><?php sidebar_render($GLOBALS['oyejo_sidebar']); ?><div class="content"><?php endif; ?>
<?php foreach (flashes() as $f) : ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
