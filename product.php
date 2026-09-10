<?php
/**
 * Oyejo Gas - product detail page (Phase 8). Unknown or inactive slugs 404.
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/catalog.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$p = null;
$related = [];
$dbError = false;

try {
    $promo = oyejo_promo_sql('p');
    $s = db()->prepare(
        "SELECT `p`.*, `c`.`name` AS `cat_name`, `c`.`slug` AS `cat_slug`,"
        . " `s`.`name` AS `size_name`, `s`.`weight_kg`, `s`.`deposit_minor`,"
        . " ($promo) AS `promo_now`"
        . " FROM `products` `p`"
        . " JOIN `categories` `c` ON `c`.`id` = `p`.`category_id`"
        . " LEFT JOIN `cylinder_sizes` `s` ON `s`.`id` = `p`.`size_id`"
        . " WHERE `p`.`slug` = ? AND `p`.`is_active` = 1 LIMIT 1"
    );
    $s->execute([$slug]);
    $p = $s->fetch() ?: null;
    if ($p) {
        $promo2 = oyejo_promo_sql('r');
        $r = db()->prepare(
            "SELECT `r`.`slug`, `r`.`name`, `r`.`price_minor`, ($promo2) AS `promo_now`,"
            . " `r`.`image`, `r`.`type`"
            . " FROM `products` `r` WHERE `r`.`category_id` = ? AND `r`.`is_active` = 1"
            . " AND `r`.`id` <> ? ORDER BY `r`.`is_featured` DESC, `r`.`sort_order` LIMIT 4"
        );
        $r->execute([$p['category_id'], $p['id']]);
        $related = $r->fetchAll();
    }
} catch (Throwable $t) {
    $dbError = true;
}

$types = oyejo_product_types();

if ($dbError) {
    $page_title = 'Unavailable';
    require BASE_PATH . '/includes/header.php';
    echo '<div class="alert alert-error">This product is temporarily unavailable. Please try again shortly.</div>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}
if (!$p) {
    http_response_code(404);
    $page_title = 'Product not found';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><p class="pill">Catalog</p><h1>Product not found</h1>'
        . '<p>That product does not exist or is no longer available.</p>'
        . '<p><a class="btn primary" href="' . e(url('shop.php')) . '">Back to shop</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$page_title = $p['name'];
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs">
  <a href="<?= e(url('')) ?>">Home</a> &rsaquo;
  <a href="<?= e(url('shop.php')) ?>">Shop</a> &rsaquo;
  <a href="<?= e(url('shop.php?cat=' . $p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a> &rsaquo;
  <?= e($p['name']) ?>
</p>

<div class="product-detail">
  <div>
    <img src="<?= e(oyejo_product_image($p)) ?>" alt="<?= e($p['name']) ?>">
  </div>
  <div>
    <p class="pill"><?= e($types[$p['type']] ?? $p['type']) ?></p>
    <h1><?= e($p['name']) ?></h1>
    <p class="price"><?= oyejo_price_html($p['price_minor'], $p['promo_now']) ?></p>
    <p><?= oyejo_stock_badge($p) ?></p>
    <?php if ($p['description'] !== null && $p['description'] !== '') : ?>
      <p><?= nl2br(e($p['description'])) ?></p>
    <?php endif; ?>
    <p><span class="badge">Cart and checkout open in Phase 9</span></p>
    <div class="table-scroll">
      <table class="data">
        <tbody>
          <tr><th>SKU</th><td><?= e($p['sku']) ?></td></tr>
          <tr><th>Category</th><td><a href="<?= e(url('shop.php?cat=' . $p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a></td></tr>
          <?php if (!empty($p['size_name'])) : ?>
            <tr><th>Cylinder size</th><td><?= e($p['size_name']) ?> (<?= e((string) $p['weight_kg']) ?> kg)</td></tr>
          <?php endif; ?>
          <?php if ($p['type'] === 'cylinder_new' && (int) ($p['deposit_minor'] ?? 0) > 0) : ?>
            <tr><th>Cylinder deposit</th><td><?= e(format_money((int) $p['deposit_minor'])) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($p['track_inventory'])) : ?>
            <tr><th>Availability</th><td><?= e(oyejo_stock_state($p)[0]) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($related) : ?>
<section>
  <h2>Related products</h2>
  <div class="grid">
    <?php foreach ($related as $r) : ?>
      <article class="card product-card">
        <a href="<?= e(url('product.php?slug=' . $r['slug'])) ?>">
          <img src="<?= e(oyejo_product_image($r)) ?>" alt="<?= e($r['name']) ?>" loading="lazy">
        </a>
        <h3><a href="<?= e(url('product.php?slug=' . $r['slug'])) ?>"><?= e($r['name']) ?></a></h3>
        <p class="price"><?= oyejo_price_html($r['price_minor'], $r['promo_now']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
