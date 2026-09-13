<?php
/**
 * Oyejo Gas - homepage (Enhanced 2026 with product showcase & plant images)
 * All DB reads fail soft so the page renders even when DB is unreachable.
 * Product images and plant images are editable via admin dashboard.
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/marketing.php';
require_once BASE_PATH . '/includes/catalog.php';

$announcements = [];
$featured = [];
$categories = [];
$banners_top = [];
$banners_bottom = [];
$sections = [];
$campaigns = [];
$products_all = [];
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
        'SELECT `slug`, `name`, `price_minor`, `size_id`, `type`, `image`,' . ' CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`' . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())' . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())' . ' THEN `promo_price_minor` END AS `promo_now`' . ' FROM `products`' . ' WHERE `is_active` = 1 AND `is_featured` = 1 ORDER BY `sort_order` LIMIT 8'
    )->fetchAll();
    $products_all = db()->query(
        'SELECT `slug`, `name`, `price_minor`, `size_id`, `type`, `image`,' . ' CASE WHEN `promo_price_minor` IS NOT NULL AND `promo_price_minor` < `price_minor`' . ' AND (`promo_starts_at` IS NULL OR `promo_starts_at` <= NOW())' . ' AND (`promo_ends_at` IS NULL OR `promo_ends_at` >= NOW())' . ' THEN `promo_price_minor` END AS `promo_now`' . ' FROM `products` WHERE `is_active` = 1 ORDER BY `is_featured` DESC, `sort_order` LIMIT 12'
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

$gallery = oyejo_product_gallery();

$plant_image = '';
$refilling_image = '';
$possible_plant = [
    'uploads/banners/plant-hero.png',
    'assets/images/plant/oyejogas-plant-hero-banner.png',
    'assets/images/plant/plant-hero.jpg',
];
$possible_refill = [
    'uploads/banners/refilling-action.png',
    'assets/images/plant/oyejogas-staff-refilling-action.png',
    'assets/images/plant/oyejogas-staff-refilling-closeup.png',
];
foreach ($possible_plant as $p) {
    if (is_file(BASE_PATH . '/' . $p)) { $plant_image = $p; break; }
}
foreach ($possible_refill as $p) {
    if (is_file(BASE_PATH . '/' . $p)) { $refilling_image = $p; break; }
}
if ($plant_image === '') $plant_image = 'assets/images/products/oyejogas-banner-all-products.png';
if ($refilling_image === '') $refilling_image = 'assets/images/plant/oyejogas-staff-refilling-closeup.png';

$page_title = 'Home';
require BASE_PATH . '/includes/header.php';
?>
<?php foreach ($banners_top as $b) : ?>
<section class="banner">
  <?php if (!empty($b['link_url'])) : ?><a href="<?= e($b['link_url']) ?>"><?php endif; ?>
  <?php if (!empty($b['image'])) : ?><img src="<?= e(preg_match('#^https?://#i', (string) $b['image']) ? $b['image'] : url((string) $b['image'])) ?>" alt="<?= e($b['title']) ?>"><?php endif; ?>
  <?php if (!empty($b['title']) || !empty($b['body'])) : ?>
    <div class="banner-copy">
      <?php if (!empty($b['title'])) : ?><strong><?= e($b['title']) ?></strong><?php endif; ?>
      <?php if (!empty($b['body'])) : ?><p><?= e($b['body']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>
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

<!-- HERO -->
<section class="hero">
  <div>
    <p class="pill">Lagos &middot; LPG, refills &amp; cylinder services</p>
    <h1>Cooking gas, delivered.</h1>
    <p class="lede">Order LPG, book refills, swap cylinders and schedule pickups — with live delivery tracking from our Owode-Egba plant.</p>
    <p class="cta">
      <?php if (is_logged_in()) : ?>
        <a class="btn primary" href="<?= e(url('customer/')) ?>">My account</a>
      <?php else : ?>
        <a class="btn primary" href="<?= e(url('customer/register.php')) ?>">Get started</a>
      <?php endif; ?>
      <a class="btn ghost" href="#products">Shop cylinders</a>
    </p>
  </div>
  <div class="hero-card">
    <h2>Why Oyejo Gas?</h2>
    <ul class="ticks">
      <li>Verified full cylinders, sealed &amp; weighed at Owode-Egba plant</li>
      <li>Same-day pickup &amp; delivery windows</li>
      <li>Wallet, transfer, card or cash payment</li>
      <li>Live order &amp; rider tracking</li>
      <li>Safety, Quality, Trust — Energy for every home</li>
    </ul>
  </div>
</section>

<!-- PRODUCT SHOWCASE - Branded Cylinders 3kg to 50kg -->
<section id="products" class="product-showcase">
  <div class="product-showcase-header">
    <p class="pill">🔥 OYEJO GAS BRANDED</p>
    <h2>All Cylinder Sizes — With OyeJo Gas Brand</h2>
    <p class="section-lead">Photorealistic, sealed &amp; safety-checked. From 3kg camping to 50kg commercial.</p>
  </div>
  
  <div class="product-grid-showcase">
    <a href="<?= e(url('shop.php?cat=lpg-cylinders')) ?>" style="text-decoration:none;">
      <div class="product-showcase-item">
        <img src="<?= e(asset('assets/images/products/oyejogas-3kg-camping.png')) ?>" alt="3kg Camping Cylinder" loading="lazy">
        <span class="size">3kg</span>
        <div class="name">Camping Small</div>
        <div class="price">Portable</div>
      </div>
    </a>
    <a href="<?= e(url('shop.php?cat=lpg-cylinders')) ?>" style="text-decoration:none;">
      <div class="product-showcase-item">
        <img src="<?= e(asset('assets/images/products/oyejogas-6kg.png')) ?>" alt="6kg Cylinder" loading="lazy">
        <span class="size">6kg</span>
        <div class="name">Small Household</div>
        <div class="price">Compact</div>
      </div>
    </a>
    <a href="<?= e(url('shop.php?cat=lpg-cylinders')) ?>" style="text-decoration:none;">
      <div class="product-showcase-item best-seller">
        <img src="<?= e(asset('assets/images/products/oyejogas-12-5kg.png')) ?>" alt="12.5kg Cylinder - Best Seller" loading="lazy">
        <span class="size">12.5kg</span>
        <div class="name">Family Standard</div>
        <div class="price">Most Popular</div>
      </div>
    </a>
    <a href="<?= e(url('shop.php?cat=lpg-cylinders')) ?>" style="text-decoration:none;">
      <div class="product-showcase-item">
        <img src="<?= e(asset('assets/images/products/oyejogas-25kg.png')) ?>" alt="25kg Cylinder" loading="lazy">
        <span class="size">25kg</span>
        <div class="name">Large Household</div>
        <div class="price">Big Family</div>
      </div>
    </a>
    <a href="<?= e(url('shop.php?cat=lpg-cylinders')) ?>" style="text-decoration:none;">
      <div class="product-showcase-item">
        <img src="<?= e(asset('assets/images/products/oyejogas-50kg.png')) ?>" alt="50kg Cylinder" loading="lazy">
        <span class="size">50kg</span>
        <div class="name">Commercial</div>
        <div class="price">Industrial</div>
      </div>
    </a>
  </div>

  <div style="text-align:center; margin-top:24px;">
    <a class="btn primary" href="<?= e(url('shop.php')) ?>">Shop All Cylinders →</a>
    <a class="btn ghost" href="<?= e(url('product-showcase.php')) ?>" style="margin-left:8px; border-color:var(--green); color:var(--green);">View Gallery (19 images)</a>
  </div>
</section>

<!-- PLANT SHOWCASE - Uploaded Image Responsive & Editable via Admin -->
<section id="plant" class="plant-showcase">
  <?php if (function_exists('current_user') && current_user() && has_permission('marketing.banners')) : ?>
    <div class="editable-hint">✏️ Editable: Admin → Marketing → Banners → plant-hero.png</div>
  <?php endif; ?>
  <img src="<?= e(asset($plant_image)) ?>" alt="OYEJO GAS LPG Refilling Plant - Owode-Egba - Safety Quality Trust - Energy for Every Home" loading="lazy">
  <div class="plant-showcase-overlay">
    <h2>OYEJO GAS LPG Refilling Plant — Owode-Egba</h2>
    <p>Safety, Quality, Trust — Energy for Every Home. State-of-the-art refilling facility with solar power, verified weighing, and sealed cylinders.</p>
    <div class="plant-showcase-badges">
      <span class="orange">🔥 LPG REFILLING PLANT</span>
      <span>✓ Safety Checked</span>
      <span>✓ Solar Powered</span>
      <span>✓ OWODE-EGBA</span>
      <span>✓ Same Day Delivery</span>
    </div>
  </div>
</section>

<!-- REFILLING ACTION BANNER - Staff refilling customer's cylinder -->
<section class="refilling-banner">
  <img src="<?= e(asset($refilling_image)) ?>" alt="OYEJO GAS staff refilling customer's gas cylinder with refilling machine at Owode-Egba plant" loading="lazy">
  <div class="refilling-banner-content">
    <p class="pill">⚙️ LIVE REFILLING ACTION</p>
    <h2>Watch Our Staff Refill Your Cylinder</h2>
    <p class="lead">Precision refilling with digital weighing at our Owode-Egba plant. Every cylinder is safety-checked, sealed, and verified.</p>
    <ul class="refilling-banner-steps">
      <li><span class="num">1</span><div><strong>Customer Brings Empty Cylinder</strong>To our plant or we pickup from your door</div></li>
      <li><span class="num">2</span><div><strong>Staff Refills with Machine</strong>Digital scale, safety valves, sealed cap</div></li>
      <li><span class="num">3</span><div><strong>Weighed &amp; Sealed for Delivery</strong>Verified full, receipt, and fast delivery to your home</div></li>
    </ul>
    <div class="cta">
      <a class="btn primary" href="<?= e(url('customer/register.php')) ?>">Book Refill Now</a>
      <a class="btn ghost" href="#how" style="border-color:var(--green); color:var(--green);">How it works</a>
    </div>
  </div>
</section>

<section id="services">
  <h2>Services</h2>
  <p class="section-lead">Everything around your cooking gas, in one place.</p>
  <div class="grid">
    <article class="card">
      <h3>Order LPG</h3>
      <p>Cylinders, refills and accessories with safe checkout.</p>
      <p><a href="#products">See LPG options &rarr;</a></p>
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
  <h2>Featured Products</h2>
  <?php if ($featured) : ?>
    <div class="grid">
      <?php foreach ($featured as $p) : 
        $img = oyejo_product_image($p);
      ?>
        <article class="card product-card">
          <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>"><img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a>
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
    <p style="text-align:center; margin-top:16px;"><a class="btn primary" href="<?= e(url('shop.php')) ?>">View All Products</a></p>
  <?php elseif ($categories) : ?>
    <div class="grid">
      <?php foreach ($categories as $c) : ?>
        <article class="card">
          <h3><?= e($c['name']) ?></h3>
          <p><?= e((string) $c['description']) ?></p>
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
      <p class="section-lead">Never run dry. We refill your cylinder or swap it for a full, sealed one at Owode-Egba plant.</p>
      <ol class="steps">
        <li><strong>Tell us</strong> your cylinder size and quantity.</li>
        <li><strong>Choose</strong> doorstep pickup or drop-off at plant.</li>
        <li><strong>Cook on</strong> — full cylinder back in hours, sealed &amp; weighed.</li>
      </ol>
    </div>
    <div class="card">
      <h3>Popular sizes</h3>
      <ul class="ticks">
        <li>3kg — camping &amp; small kitchens</li>
        <li>6kg — flats &amp; small kitchens</li>
        <li>12.5kg — family standard (best seller)</li>
        <li>25kg &amp; 50kg — commercial &amp; industrial</li>
      </ul>
      <p><a class="btn primary" href="<?= e(url('customer/register.php')) ?>">Book a refill</a></p>
    </div>
  </div>
</section>

<section id="pickup">
  <h2>Pickup &amp; exchange</h2>
  <p class="section-lead">Empty cylinder? We collect it, check it at our plant, and exchange or return it refilled.</p>
  <div class="grid">
    <article class="card"><h3>Schedule</h3><p>Pick a day and time window that suits you.</p></article>
    <article class="card"><h3>We collect</h3><p>Our rider or tricycle picks up empties from your door.</p></article>
    <article class="card"><h3>You cook</h3><p>Full, sealed cylinder handed over with receipt from Owode-Egba.</p></article>
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
    <li><strong>Relax</strong> — we pick up, refill at Owode-Egba plant, and deliver.</li>
  </ol>
  <p class="cta"><a class="btn primary" href="<?= e(url('contact.php')) ?>">Talk to us</a> <a class="btn ghost" href="<?= e(url('shop.php')) ?>" style="border-color:var(--green); color:var(--green);">Shop Now</a></p>
</section>

<?php foreach ($banners_bottom as $b) : ?>
<section class="banner">
  <?php if (!empty($b['link_url'])) : ?><a href="<?= e($b['link_url']) ?>"><?php endif; ?>
  <?php if (!empty($b['image'])) : ?><img src="<?= e(preg_match('#^https?://#i', (string) $b['image']) ? $b['image'] : url((string) $b['image'])) ?>" alt="<?= e($b['title']) ?>"><?php endif; ?>
  <?php if (!empty($b['title']) || !empty($b['body'])) : ?>
    <div class="banner-copy">
      <?php if (!empty($b['title'])) : ?><strong><?= e($b['title']) ?></strong><?php endif; ?>
      <?php if (!empty($b['body'])) : ?><p><?= e($b['body']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($b['link_url'])) : ?></a><?php endif; ?>
</section>
<?php endforeach; ?>

<?php foreach ($sections as $s) : ?>
<section class="homepage-section-card">
  <h2><span class="icon">✦</span> <?= e($s['title']) ?></h2>
  <?= mk_render_blocks($s['content'] ?? '') ?>
</section>
<?php endforeach; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>
