<?php
/**
 * Oyejo Gas - shared page header.
 * Full public-site design arrives in Phase 7; this is the working shell.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
$nav = [
    ['Home', url('')],
    ['My account', url('customer/')],
    ['Driver', url('driver/')],
    ['Admin', url('admin/')],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($page_title ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('assets/images/logo.svg')) ?>" type="image/svg+xml">
</head>
<body>
<?php if (oyejo_feature('maintenance_mode')) : ?>
<div class="maint" role="alert">Scheduled maintenance is in progress. Some services may be unavailable.</div>
<?php endif; ?>
<header class="site">
  <div class="wrap nav">
    <a class="brand" href="<?= e(url('')) ?>">
      <img src="<?= e(asset('assets/images/logo.svg')) ?>" alt="" width="36" height="36">
      <span><?= e(APP_NAME) ?></span>
    </a>
    <button class="hamburger" id="navToggle" aria-label="Menu" aria-expanded="false">&#9776;</button>
    <nav class="links" id="navLinks">
      <?php foreach ($nav as $item) : ?>
        <a href="<?= e($item[1]) ?>"><?= e($item[0]) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach (flashes() as $f) : ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
