<?php
/**
 * Oyejo Gas - homepage (foundation stub; full site in Phase 7).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
$page_title = 'Home';
require BASE_PATH . '/includes/header.php';
?>
<section class="hero">
  <div>
    <p class="pill">Lagos &middot; LPG, refills &amp; cylinder services</p>
    <h1>Cooking gas, delivered.</h1>
    <p class="lede">Order LPG, book refills, swap cylinders and schedule pickups &mdash; with live delivery tracking.</p>
    <p class="cta">
      <a class="btn primary" href="<?= e(url('customer/')) ?>">My account</a>
      <a class="btn ghost" href="#services">Explore services</a>
    </p>
  </div>
  <div class="hero-card">
    <h2>Foundation status: OK</h2>
    <p>Phase 2 project skeleton is live. Storefront modules unlock in Phases 7&ndash;10.</p>
    <ul class="ticks">
      <li>Secure config &amp; sessions</li>
      <li>Protected uploads, logs &amp; backups</li>
      <li>Customer &middot; Driver &middot; Admin portals</li>
    </ul>
  </div>
</section>

<section id="services">
  <h2>Services</h2>
  <div class="grid">
    <article class="card">
      <h3>Order LPG</h3>
      <p>Cylinders, refills and accessories with safe checkout.</p>
      <p><span class="badge">Coming in Phases 8&ndash;9</span></p>
    </article>
    <article class="card">
      <h3>Gas refill</h3>
      <p>Book a refill by cylinder size, with pickup or delivery.</p>
      <p><span class="badge">Coming in Phase 12</span></p>
    </article>
    <article class="card">
      <h3>Pickup &amp; exchange</h3>
      <p>We collect empties and swap cylinders at your door.</p>
      <p><span class="badge">Coming in Phase 13</span></p>
    </article>
    <article class="card">
      <h3>Track delivery</h3>
      <p>Live status from dispatch to your doorstep.</p>
      <p><span class="badge">Coming in Phase 16</span></p>
    </article>
  </div>
</section>

<section>
  <h2>How it works</h2>
  <ol class="steps">
    <li><strong>Choose</strong> your cylinder size or refill.</li>
    <li><strong>Check out</strong> with wallet, transfer, card or cash.</li>
    <li><strong>Relax</strong> &mdash; we pick up, refill and deliver.</li>
  </ol>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
