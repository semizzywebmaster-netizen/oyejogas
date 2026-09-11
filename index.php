<?php
/**
 * Oyejo Gas - homepage (Phase 7). All DB reads fail soft so the page
 * renders even when the database is unreachable.
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/marketing.php';

$announcements = [];
$featured = [];
$categories = [];
$banners_top = [];
$banners_bottom = [];
$sections = [];
$campaigns = [];
$mkt = oyejo_feature('marketing_campaigns');
try {
    if ($mkt) {
    $announcements = db()->query(
        "SELECT `title`, `body` FROM `announcements` WHERE `is_active` = 1 AND `audience` = 'all'
         AND (`starts_at` IS NULL OR `starts_at` <= NOW()) AND (`ends_at` IS NULL OR `ends_at` >= NOW())
         ORDER BY `id` DESC LIMIT 3"
    )->fetchAll();
    }
    $featured = db()->query(
        'SELECT `slug`, `name`, `price_minor`,'
        . ' CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`'
        . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())'
        . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())'
        . ' THEN `promo_price_minor` END AS `promo_now`'
        . ' FROM `products`'
        . ' WHERE `is_active` = 1 AND `is_featured` = 1 ORDER BY `sort_order` LIMIT 4'
    )->fetchAll();
    $categories = db()->query(
        'SELECT `name`, `description` FROM `categories` WHERE `is_active` = 1 ORDER BY `sort_order` LIMIT 4'
    )->fetchAll();
    $banners_top = mk_banners_live('home_top');
    $banners_bottom = mk_banners_live('home_bottom');
    $sections = mk_sections_live();
    $campaigns = mk_campaigns_live();
} catch (Throwable $t) {
    // Fail soft: static content below still renders.
}

$page_title = 'Home';
require BASE_PATH . '/includes/header.php';
?>
<?php foreach ($banners_top as $b) : ?>
<section class="banner">
  <?php if (!empty($b['link_url'])) : ?><a href="<?= e($b['link_url']) ?>"><?php endif; ?>
  <?php if (!empty($b['image'])) : ?><img src="<?= e(preg_match('#^https?://#i', (string) $b['image']) ? $b['image'] : url((string) $b['image'])) ?>" alt="<?= e($b['title']) ?>"><?php else : ?><strong><?= e($b['title']) ?></strong><?php endif; ?>
  <?php if (!empty($b['link_url'])) : ?></a><?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($campaigns) : ?>
<section class="campaigns">
  <h2>Current offers</h2>
  <div class="grid">
    <?php foreach ($campaigns as $c) : ?>
      <article class="card"><h3><?= e($c['name']) ?></h3>
        <?php if (!empty($c['description'])) : ?><p><?= e($c['description']) ?></p><?php endif; ?></article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if ($announcements) : ?>
<section class="announce">
  <?php foreach ($announcements as $a) : ?>
    <div class="alert alert-info"><strong><?= e($a['title']) ?></strong> — <?= e($a['body']) ?></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="hero">
  <div>
    <p class="pill">Lagos &middot; LPG, refills &amp; cylinder services</p>
    <h1>Cooking gas, delivered.</h1>
    <p class="lede">Order LPG, book refills, swap cylinders and schedule pickups &mdash; with live delivery tracking.</p>
    <p class="cta">
      <?php if (is_logged_in()) : ?>
        <a class="btn primary" href="<?= e(url('customer/')) ?>">My account</a>
      <?php else : ?>
        <a class="btn primary" href="<?= e(url('customer/register.php')) ?>">Get started</a>
      <?php endif; ?>
      <a class="btn ghost" href="#services">Explore services</a>
    </p>
  </div>
  <div class="hero-card">
    <h2>Why Oyejo Gas?</h2>
    <ul class="ticks">
      <li>Verified full cylinders, sealed &amp; weighed</li>
      <li>Same-day pickup &amp; delivery windows</li>
      <li>Wallet, transfer, card or cash payment</li>
      <li>Live order &amp; rider tracking</li>
    </ul>
  </div>
</section>

<section id="services">
  <h2>Services</h2>
  <p class="section-lead">Everything around your cooking gas, in one place.</p>
  <div class="grid">
    <article class="card">
      <h3>Order LPG</h3>
      <p>Cylinders, refills and accessories with safe checkout.</p>
      <p><a href="#lpg">See LPG options &rarr;</a></p>
    </article>
    <article class="card">
      <h3>Gas refill</h3>
      <p>Book a refill by cylinder size, with pickup or delivery.</p>
      <p><a href="#refill">How refills work &rarr;</a></p>
    </article>
    <article class="card">
      <h3>Pickup &amp; exchange</h3>
      <p>We collect empties and swap cylinders at your door.</p>
      <p><a href="#pickup">How pickup works &rarr;</a></p>
    </article>
    <article class="card">
      <h3>Fast delivery</h3>
      <p>Zoned fees, time slots and live rider tracking.</p>
      <p><a href="#delivery">Delivery info &rarr;</a></p>
    </article>
  </div>
</section>

<section id="lpg">
  <h2>LPG products</h2>
  <?php if ($featured) : ?>
    <div class="grid">
      <?php foreach ($featured as $p) : ?>
        <article class="card">
          <h3><a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
          <p class="price">
            <?php if ($p['promo_now'] !== null) : ?>
              <del><?= e(format_money($p['price_minor'])) ?></del>
              <strong><?= e(format_money($p['promo_now'])) ?></strong>
            <?php else : ?>
              <strong><?= e(format_money($p['price_minor'])) ?></strong>
            <?php endif; ?>
          </p>
          <p><a class="btn ghost" href="<?= e(url('product.php?slug=' . $p['slug'])) ?>">View</a></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php elseif ($categories) : ?>
    <div class="grid">
      <?php foreach ($categories as $c) : ?>
        <article class="card">
          <h3><?= e($c['name']) ?></h3>
          <p><?= e((string) $c['description']) ?></p>
          <p><span class="badge">Shop opens in Phase 8</span></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="card"><p>Our product catalogue is being stocked. Check back soon.</p></div>
  <?php endif; ?>
</section>

<section id="refill">
  <h2>Gas refill</h2>
  <div class="split">
    <div>
      <p class="section-lead">Never run dry. We refill your cylinder or swap it for a full, sealed one.</p>
      <ol class="steps">
        <li><strong>Tell us</strong> your cylinder size and quantity.</li>
        <li><strong>Choose</strong> doorstep pickup or drop-off.</li>
        <li><strong>Cook on</strong> — full cylinder back in hours.</li>
      </ol>
    </div>
    <div class="card">
      <h3>Popular sizes</h3>
      <ul class="ticks">
        <li>6kg — flats &amp; small kitchens</li>
        <li>12.5kg — family standard</li>
        <li>25kg &amp; 50kg — commercial</li>
      </ul>
      <p><a class="btn primary" href="<?= e(url('customer/register.php')) ?>">Book a refill</a></p>
    </div>
  </div>
</section>

<section id="pickup">
  <h2>Pickup &amp; exchange</h2>
  <p class="section-lead">Empty cylinder? We collect it, check it, and exchange or return it refilled.</p>
  <div class="grid">
    <article class="card"><h3>Schedule</h3><p>Pick a day and time window that suits you.</p></article>
    <article class="card"><h3>We collect</h3><p>Our rider picks up empties from your door.</p></article>
    <article class="card"><h3>You cook</h3><p>Full, sealed cylinder handed over with receipt.</p></article>
  </div>
</section>

<section id="delivery">
  <h2>Delivery</h2>
  <div class="split">
    <div class="card">
      <h3>Zoned &amp; predictable</h3>
      <ul class="ticks">
        <li>Clear per-zone fees, shown before you pay</li>
        <li>Morning, afternoon &amp; evening windows</li>
        <li>Live status from dispatch to doorstep</li>
      </ul>
    </div>
    <div class="card">
      <h3>Pay your way</h3>
      <ul class="ticks">
        <li>Wallet, card, bank transfer or cash</li>
        <li>Instant receipts &amp; invoices</li>
        <li>Refunds straight to your wallet</li>
      </ul>
    </div>
  </div>
</section>

<section id="how">
  <h2>How it works</h2>
  <ol class="steps">
    <li><strong>Choose</strong> your cylinder size or refill.</li>
    <li><strong>Check out</strong> with wallet, transfer, card or cash.</li>
    <li><strong>Relax</strong> &mdash; we pick up, refill and deliver.</li>
  </ol>
  <p class="cta"><a class="btn primary" href="<?= e(url('contact.php')) ?>">Talk to us</a></p>
</section>
<?php foreach ($banners_bottom as $b) : ?>
<section class="banner">
  <?php if (!empty($b['link_url'])) : ?><a href="<?= e($b['link_url']) ?>"><?php endif; ?>
  <?php if (!empty($b['image'])) : ?><img src="<?= e(preg_match('#^https?://#i', (string) $b['image']) ? $b['image'] : url((string) $b['image'])) ?>" alt="<?= e($b['title']) ?>"><?php else : ?><strong><?= e($b['title']) ?></strong><?php endif; ?>
  <?php if (!empty($b['link_url'])) : ?></a><?php endif; ?>
</section>
<?php endforeach; ?>
<?php foreach ($sections as $s) : ?>
<section>
  <h2><?= e($s['title']) ?></h2>
  <?= mk_render_blocks($s['content'] ?? '') ?>
</section>
<?php endforeach; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
