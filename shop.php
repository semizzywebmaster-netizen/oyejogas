<?php
/**
 * Oyejo Gas - product catalog storefront (Phase 8).
 * Search + category filter + sort + pagination. Only active products listed.
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/catalog.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (strlen($q) > 80) {
    $q = substr($q, 0, 80);
}
$catSlug = trim((string) ($_GET['cat'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'featured');
$sorts = [
    'featured'   => '`p`.`is_featured` DESC, `p`.`sort_order` ASC, `p`.`name` ASC',
    'price_asc'  => '`price_now` ASC, `p`.`name` ASC',
    'price_desc' => '`price_now` DESC, `p`.`name` ASC',
    'name'       => '`p`.`name` ASC',
];
if (!isset($sorts[$sort])) {
    $sort = 'featured';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 24;

$categories = [];
$products = [];
$total = 0;
$pages = 1;
$cat = null;
$dbError = false;

try {
    $categories = db()->query(
        'SELECT `c`.*, (SELECT COUNT(*) FROM `products` `p`'
        . ' WHERE `p`.`category_id` = `c`.`id` AND `p`.`is_active` = 1) AS `n`'
        . ' FROM `categories` `c` WHERE `c`.`is_active` = 1 ORDER BY `c`.`sort_order`'
    )->fetchAll();
    if ($catSlug !== '') {
        $s = db()->prepare('SELECT * FROM `categories` WHERE `slug` = ? AND `is_active` = 1 LIMIT 1');
        $s->execute([$catSlug]);
        $cat = $s->fetch() ?: null; // unknown slug: fall back to all products
    }
    $where = ['`p`.`is_active` = 1'];
    $args = [];
    if ($cat) {
        $where[] = '`p`.`category_id` = ?';
        $args[] = $cat['id'];
    }
    if ($q !== '') {
        // '!' escape (not backslash): safe under both native and emulated PDO prepares.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $where[] = '(`p`.`name` LIKE ? ESCAPE \'!\' OR `p`.`description` LIKE ? ESCAPE \'!\''
            . ' OR `p`.`sku` LIKE ? ESCAPE \'!\')';
        array_push($args, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $s = db()->prepare("SELECT COUNT(*) FROM `products` `p` WHERE $w");
    $s->execute($args);
    $total = (int) $s->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    if ($page > $pages) {
        $page = $pages;
    }
    $promo = oyejo_promo_sql('p');
    $off = ($page - 1) * $per;
    $s = db()->prepare(
        "SELECT `p`.*, `c`.`name` AS `cat_name`, `c`.`slug` AS `cat_slug`,"
        . " `s`.`name` AS `size_name`, ($promo) AS `promo_now`,"
        . " COALESCE(($promo), `p`.`price_minor`) AS `price_now`"
        . " FROM `products` `p`"
        . " JOIN `categories` `c` ON `c`.`id` = `p`.`category_id`"
        . " LEFT JOIN `cylinder_sizes` `s` ON `s`.`id` = `p`.`size_id`"
        . " WHERE $w ORDER BY {$sorts[$sort]} LIMIT $per OFFSET $off"
    );
    $s->execute($args);
    $products = $s->fetchAll();
} catch (Throwable $t) {
    $dbError = true;
}

function shop_url(array $over = []) {
    $p = ['q' => trim((string) ($_GET['q'] ?? '')), 'cat' => trim((string) ($_GET['cat'] ?? '')),
        'sort' => (string) ($_GET['sort'] ?? 'featured'), 'page' => (int) ($_GET['page'] ?? 1)];
    foreach ($over as $k => $v) {
        $p[$k] = $v;
    }
    if (($p['page'] ?? 1) < 1) {
        $p['page'] = 1;
    }
    return url('shop.php?' . http_build_query($p));
}

$types = oyejo_product_types();
$wish_ids = [];
if (is_logged_in() && function_exists('wish_id_set')) {
    try {
        $wish_ids = wish_id_set(wish_customer_id((int) current_user()['id']));
    } catch (Throwable $t) {
        $wish_ids = [];
    }
}
$page_title = $cat ? ('Shop ' . $cat['name']) : 'Shop';
if (function_exists('daily_mark_seen') && is_logged_in()) {
    daily_mark_seen('shop');
}
require BASE_PATH . '/includes/header.php';
?>
<div class="page-hero">
  <p class="pill">Catalog</p>
  <h1><?= $cat ? e($cat['name']) : 'Shop LPG products' ?></h1>
  <?php if ($cat && $cat['description'] !== null && $cat['description'] !== '') : ?>
    <p class="section-lead"><?= e($cat['description']) ?></p>
  <?php endif; ?>
</div>

<?php if ($dbError) : ?>
  <div class="alert alert-error">The catalog is temporarily unavailable. Please try again shortly.</div>
<?php else : ?>
<div class="shop-layout">
  <aside class="shop-side">
    <div class="card">
      <form method="get" action="<?= e(url('shop.php')) ?>" class="stack">
        <label>Search products
          <input name="q" value="<?= e($q) ?>" maxlength="80" placeholder="e.g. regulator, 12.5kg">
        </label>
        <?php if ($catSlug !== '') : ?>
          <input type="hidden" name="cat" value="<?= e($catSlug) ?>">
        <?php endif; ?>
        <label>Sort by
          <select name="sort">
            <option value="featured"<?= $sort === 'featured' ? ' selected' : '' ?>>Featured</option>
            <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: low to high</option>
            <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: high to low</option>
            <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name A–Z</option>
          </select>
        </label>
        <p><button class="btn primary" type="submit">Apply</button></p>
      </form>
    </div>
    <div class="card">
      <strong>Categories</strong>
      <ul>
        <li><a href="<?= e(shop_url(['cat' => '', 'page' => 1])) ?>"<?= $cat ? '' : ' class="on"' ?>>All products</a></li>
        <?php foreach ($categories as $c) : ?>
          <li>
            <a href="<?= e(shop_url(['cat' => $c['slug'], 'page' => 1])) ?>"<?= $cat && (int) $cat['id'] === (int) $c['id'] ? ' class="on"' : '' ?>>
              <?= e($c['name']) ?> <span class="n">(<?= (int) $c['n'] ?>)</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <div>
    <p class="result-meta">
      <?= $total === 1 ? '1 product' : $total . ' products' ?>
      <?php if ($q !== '') : ?>matching &ldquo;<?= e($q) ?>&rdquo;<?php endif; ?>
    </p>
    <?php if (!$products) : ?>
      <div class="card"><p>No products found. Try a different search or category.</p></div>
    <?php else : ?>
      <div class="grid">
        <?php foreach ($products as $p) : ?>
          <article class="card product-card">
            <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>">
              <img src="<?= e(oyejo_product_image($p)) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            </a>
            <p><span class="badge"><?= e($types[$p['type']] ?? $p['type']) ?></span>
              <?php if (!empty($p['size_name'])) : ?><span class="badge"><?= e($p['size_name']) ?></span><?php endif; ?>
            </p>
            <h3><a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
            <p class="price"><?= oyejo_price_html($p['price_minor'], $p['promo_now']) ?></p>
            <p><?= oyejo_stock_badge($p) ?></p>
            <?= wish_button((int) $p['id'], isset($wish_ids[(int) $p['id']])) ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1) : ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($page > 1) : ?>
            <a class="btn ghost" href="<?= e(shop_url(['page' => $page - 1])) ?>">&larr; Prev</a>
          <?php endif; ?>
          <span>Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages) : ?>
            <a class="btn ghost" href="<?= e(shop_url(['page' => $page + 1])) ?>">Next &rarr;</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
