<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Download OyeJo Gas Product Images';
require BASE_PATH . '/includes/header.php';
?>
<div style="max-width:800px; margin:2rem auto; text-align:center;">
  <div style="background: linear-gradient(135deg, #0b6b3a 0%, #0f8a4d 100%); color:white; padding:2.5rem; border-radius:16px;">
    <h1>📦 OyeJo Gas Branded Images Pack</h1>
    <p style="font-size:1.2rem; opacity:0.9;">All cylinder sizes with OyeJo Gas brand - Ready for download</p>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin:2rem 0; text-align:left;">
    <div class="card">
      <h3>🎯 Complete Pack (Recommended)</h3>
      <p><strong>File:</strong> oyejogas-complete-pack.zip</p>
      <p><strong>Size:</strong> 29 MB</p>
      <p><strong>Contains:</strong></p>
      <ul>
        <li>19 PNG images (all sizes + variants)</li>
        <li>Organized folder structure</li>
        <li>README with brand guidelines</li>
        <li>Marketing banners & lifestyle hero</li>
      </ul>
      <p><a href="<?= e(asset('oyejogas-complete-pack.zip')) ?>" class="btn primary" download>⬇️ Download Complete Pack</a></p>
    </div>
    <div class="card">
      <h3>📁 Images Only Pack</h3>
      <p><strong>File:</strong> assets/images/products/oyejogas-branded-products.zip</p>
      <p><strong>Size:</strong> 29 MB</p>
      <p><strong>Contains:</strong></p>
      <ul>
        <li>20 PNG files (flat, no folders)</li>
        <li>All cylinder sizes 3kg to 50kg</li>
        <li>Refill + New variants</li>
        <li>Accessories & concepts</li>
      </ul>
      <p><a href="<?= e(asset('assets/images/products/oyejogas-branded-products.zip')) ?>" class="btn ghost" download>⬇️ Download Images Only</a></p>
    </div>
  </div>

  <div class="card" style="text-align:left;">
    <h3>📋 What's Inside</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:1rem; font-size:0.9rem;">
      <div>
        <strong>Core Sizes:</strong>
        <ul>
          <li>3kg Camping</li>
          <li>6kg</li>
          <li>12.5kg (Best Seller)</li>
          <li>25kg</li>
          <li>50kg</li>
        </ul>
      </div>
      <div>
        <strong>Variants:</strong>
        <ul>
          <li>New (factory shiny)</li>
          <li>Refill (sealed)</li>
          <li>Family lineup</li>
          <li>Exchange concept</li>
        </ul>
      </div>
      <div>
        <strong>Marketing:</strong>
        <ul>
          <li>Banner 16:9</li>
          <li>Lifestyle hero (Lagos delivery)</li>
          <li>Regulator + hose</li>
          <li>Double burner</li>
        </ul>
      </div>
      <div>
        <strong>Brand:</strong>
        <ul>
          <li>Green #0b6b3a</li>
          <li>Orange #ff9d2e flame</li>
          <li>OYEJO GAS white text</li>
          <li>White background</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="card" style="background:#f6f8f6; margin-top:1.5rem;">
    <h3>🚀 Already Integrated</h3>
    <p>These images are <strong>already live</strong> in your shop:</p>
    <ul style="text-align:left; max-width:500px; margin:1rem auto;">
      <li>Shop auto-shows branded cylinders by size_id</li>
      <li>Product pages use branded images</li>
      <li>Showcase gallery at <a href="<?= e(url('product-showcase.php')) ?>">/product-showcase.php</a></li>
    </ul>
  </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
