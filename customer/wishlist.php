<?php
/**
 * Oyejo Gas - customer wishlist.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/catalog.php';
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/wishlist.php';

$me = current_user();
$cid = wish_customer_id((int) $me['id']);
if ($cid < 1) {
    $cid = cart_customer_id((int) $me['id']);
}

$next_raw = (string) post('next', '');
$safe_back = function ($fallback) use ($next_raw) {
    return safe_next($next_raw, $fallback);
};

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        flash('error', 'Security token mismatch. Reload and try again.');
        redirect(url('customer/wishlist.php'));
    }
    $action = (string) post('action', '');
    $pid = (int) post('product_id', 0);
    if ($action === 'add') {
        [$ok, $msg] = wish_add($cid, $pid);
        flash($ok ? 'success' : 'error', $msg);
        redirect($safe_back(url('customer/wishlist.php')));
    }
    if ($action === 'remove') {
        [$ok, $msg] = wish_remove($cid, $pid);
        flash($ok ? 'success' : 'error', $msg);
        redirect($safe_back(url('customer/wishlist.php')));
    }
    if ($action === 'clear') {
        [$ok, $msg] = wish_clear($cid);
        flash($ok ? 'success' : 'error', $msg);
        redirect(url('customer/wishlist.php'));
    }
    if ($action === 'to_cart') {
        [$ok, $msg] = cart_add($pid, 1);
        flash($ok ? 'success' : 'error', $ok ? 'Added to cart.' : $msg);
        redirect(url('customer/wishlist.php'));
    }
    if ($action === 'to_cart_all') {
        $n = 0;
        $fail = 0;
        foreach (wish_list($cid) as $row) {
            [$ok] = cart_add((int) $row['id'], 1);
            $ok ? $n++ : $fail++;
        }
        flash($n > 0 ? 'success' : 'error', $n > 0
            ? ($n . ' item' . ($n === 1 ? '' : 's') . ' added to cart' . ($fail ? ' (' . $fail . ' skipped)' : '') . '.')
            : 'Nothing could be added to the cart.');
        redirect($n > 0 ? url('cart.php') : url('customer/wishlist.php'));
    }
    flash('error', 'Unknown action.');
    redirect(url('customer/wishlist.php'));
}

$items = [];
try {
    $items = wish_list($cid);
} catch (Throwable $t) {
    $items = [];
}
$types = oyejo_product_types();

$page_title = 'Wishlist';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Wishlist</p>
<h1>Wishlist</h1>
<p class="section-lead">Save cylinders, refills and accessories for later. Prices shown are live — they update if a promo starts or ends.</p>

<?php if (!$items) : ?>
  <div class="card">
    <p>Your wishlist is empty.</p>
    <p><a class="btn primary" href="<?= e(url('shop.php')) ?>">Browse the shop</a></p>
  </div>
<?php else : ?>
  <p class="result-meta"><?= count($items) ?> saved item<?= count($items) === 1 ? '' : 's' ?>
    · <a href="<?= e(url('shop.php')) ?>">Keep shopping</a></p>
  <div class="cta">
    <form method="post" action="" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="to_cart_all">
      <button class="btn primary" type="submit">Add all to cart</button>
    </form>
    <form method="post" action="" class="inline-form" onsubmit="return confirm('Clear the whole wishlist?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear">
      <button class="btn ghost" type="submit">Clear wishlist</button>
    </form>
  </div>
  <div class="grid">
    <?php foreach ($items as $p) : ?>
      <article class="card product-card">
        <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>">
          <img src="<?= e(oyejo_product_image($p)) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        </a>
        <p><span class="badge"><?= e($types[$p['type']] ?? $p['type']) ?></span></p>
        <h3><a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
        <p class="price"><?= oyejo_price_html($p['price_minor'], $p['promo_now']) ?></p>
        <p><?= oyejo_stock_badge($p) ?></p>
        <p class="result-meta">Saved <?= e(substr((string) $p['wished_at'], 0, 10)) ?></p>
        <div class="cta">
          <?php if (oyejo_feature('product_ordering') && oyejo_stock_state($p)[0] !== 'Out of stock') : ?>
            <form method="post" action="" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="to_cart">
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <button class="btn small primary" type="submit">Add to cart</button>
            </form>
          <?php endif; ?>
          <form method="post" action="" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
            <button class="btn small ghost" type="submit">Remove</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
