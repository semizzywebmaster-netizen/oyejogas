<?php
/**
 * OyeJo Gas - Branded Product Showcase
 * Displays all generated OyeJogas cylinder images
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/catalog.php';

$page_title = 'OyeJo Gas Product Showcase - All Cylinder Sizes';
require BASE_PATH . '/includes/header.php';

$gallery = oyejo_product_gallery();
$all_images = [];
foreach ($gallery as $group) {
    foreach ($group['images'] as $img) {
        $all_images[] = $img;
    }
}
?>
<style>
.showcase-hero { text-align:center; padding:2rem 1rem; background: linear-gradient(135deg, #0b6b3a 0%, #0f8a4d 100%); color:white; border-radius:16px; margin-bottom:2rem; }
.showcase-hero h1 { font-size:2.5rem; margin:0.5rem 0; }
.showcase-hero p { font-size:1.2rem; opacity:0.9; }
.product-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:1.5rem; }
.product-showcase-card { background:white; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); transition: transform 0.2s; }
.product-showcase-card:hover { transform: translateY(-4px); box-shadow:0 8px 30px rgba(0,0,0,0.12); }
.product-showcase-card img { width:100%; height:300px; object-fit:contain; background:#f8faf8; padding:1rem; }
.product-showcase-card .info { padding:1.2rem; }
.product-showcase-card .size-badge { display:inline-block; background:#0b6b3a; color:white; padding:0.2rem 0.8rem; border-radius:20px; font-size:0.85rem; font-weight:600; }
.product-showcase-card .label { font-weight:700; margin:0.5rem 0; font-size:1.1rem; }
.product-showcase-card .path { font-size:0.75rem; color:#888; font-family:monospace; word-break:break-all; }
.family-banner { grid-column:1 / -1; }
.family-banner img { height:400px; }
</style>

<div class="showcase-hero">
  <p class="pill" style="background:#ff9d2e; color:#000;">🔥 OYEJO GAS BRANDED</p>
  <h1>All Cylinder Sizes with OyeJo Gas Brand</h1>
  <p>Photorealistic product shots • Green #0b6b3a + Orange #ff9d2e • White background e-commerce ready</p>
  <p><strong><?= count($all_images) ?> images</strong> generated for shop, catalog & marketing</p>
</div>

<?php foreach ($gallery as $key => $group): ?>
  <h2 style="margin:2.5rem 0 1rem; border-left:4px solid #0b6b3a; padding-left:1rem;">
    <?= htmlspecialchars($group['label']) ?> <span style="color:#888; font-weight:normal;">(<?= htmlspecialchars($group['size']) ?>)</span>
  </h2>
  <div class="product-grid">
    <?php foreach ($group['images'] as $imgPath):
      $fullPath = BASE_PATH . '/' . $imgPath;
      $exists = is_file($fullPath);
      $url = $exists ? asset($imgPath) : asset('assets/images/product-placeholder.svg');
    ?>
      <div class="product-showcase-card <?= $key==='family' ? 'family-banner' : '' ?>">
        <img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($group['label']) ?>" loading="lazy">
        <div class="info">
          <span class="size-badge"><?= htmlspecialchars($group['size']) ?></span>
          <div class="label"><?= htmlspecialchars($group['label']) ?></div>
          <div class="path"><?= htmlspecialchars($imgPath) ?> <?= $exists ? '✅' : '⏳ pending' ?></div>
          <?php if ($exists): ?>
            <p style="margin-top:0.8rem;"><a class="btn small primary" href="<?= htmlspecialchars($url) ?>" target="_blank">View Full Size</a></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="card" style="margin-top:3rem; background:#f6f8f6;">
  <h3>How to Use These Images</h3>
  <ul>
    <li><strong>Shop Integration:</strong> Images are auto-mapped in <code>includes/catalog.php</code> via <code>oyejo_product_image()</code> - products with size_id will automatically show branded cylinders</li>
    <li><strong>Admin Assignment:</strong> Go to Admin → Products → Edit → set Image to <code>assets/images/products/oyejogas-*.png</code></li>
    <li><strong>Database Update:</strong> Run the migration below to assign images to seeded products</li>
    <li><strong>Brand Consistency:</strong> All cylinders use #0b6b3a green + white OYEJO GAS text + orange flame logo</li>
  </ul>
  <h4>SQL to assign images to products (run in phpMyAdmin or via migration)</h4>
  <pre style="background:#fff; padding:1rem; border-radius:8px; overflow:auto; font-size:0.85rem;">
-- Assign branded images to products
UPDATE products SET image='assets/images/products/oyejogas-3kg-camping.png' WHERE sku='CYL-3KG-NEW' OR slug LIKE '%3kg%';
UPDATE products SET image='assets/images/products/oyejogas-6kg.png' WHERE size_id=2 AND type='cylinder_new';
UPDATE products SET image='assets/images/products/oyejogas-6kg-refill.png' WHERE slug='refill-6kg';
UPDATE products SET image='assets/images/products/oyejogas-12-5kg.png' WHERE slug='new-cylinder-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-12-5kg-refill.png' WHERE slug='refill-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-12-5kg-new.png' WHERE sku='CYL-125-NEW';
UPDATE products SET image='assets/images/products/oyejogas-exchange-concept.png' WHERE slug='exchange-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-accessories-regulator.png' WHERE slug='regulator-hose-set';
UPDATE products SET image='assets/images/products/oyejogas-accessories-burner.png' WHERE slug='table-top-burner';
  </pre>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
