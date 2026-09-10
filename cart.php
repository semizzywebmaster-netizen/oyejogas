<?php
/**
 * Oyejo Gas - shopping cart (Phase 9). Session cart; all money server-side.
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
require_once BASE_PATH . '/includes/cart.php';

if (!oyejo_feature('product_ordering')) {
    http_response_code(403);
    $page_title = 'Ordering disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Ordering is currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$message = '';
$error = '';

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $error = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'add') {
            list($ok, $msg) = cart_add(post('product_id'), post('qty', 1));
            if ($ok) {
                redirect(url('cart.php?added=1'));
            }
            $error = $msg;
        } elseif ($action === 'update') {
            $qtys = post('qty', []);
            if (is_array($qtys)) {
                foreach ($qtys as $pid => $q) {
                    list($ok, $msg) = cart_set_qty($pid, $q);
                    if (!$ok) {
                        $error = $msg;
                    }
                }
            }
            if ($error === '') {
                $message = 'Cart updated.';
            }
        } elseif ($action === 'remove') {
            list($ok, $msg) = cart_set_qty(post('product_id'), 0);
            $message = $msg;
        } elseif ($action === 'clear') {
            cart_clear();
            $message = 'Cart cleared.';
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Added to cart.';
}

list($lines, $notices) = cart_lines();
$subtotal = cart_subtotal($lines);

$page_title = 'Cart';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-hero">
  <p class="pill">Cart</p>
  <h1>Your cart</h1>
</div>
<?php foreach ($notices as $n) : ?>
  <div class="alert alert-info"><?= e($n) ?></div>
<?php endforeach; ?>
<?php if ($message !== '') : ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error !== '') : ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$lines) : ?>
  <div class="card"><p>Your cart is empty.</p><p><a class="btn primary" href="<?= e(url('shop.php')) ?>">Browse the shop</a></p></div>
<?php else : ?>
  <form method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="table-scroll">
      <table class="data cart-table">
        <thead><tr><th>Product</th><th>Unit price</th><th>Qty</th><th>Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lines as $l) : ?>
            <tr>
              <td><a href="<?= e(url('product.php?slug=' . $l['slug'])) ?>"><?= e($l['name']) ?></a></td>
              <td><?= e(format_money($l['unit'])) ?></td>
              <td><input type="number" name="qty[<?= (int) $l['id'] ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="99"></td>
              <td><?= e(format_money($l['total'])) ?></td>
              <td>
                <button class="btn ghost" type="submit" formaction="<?= e(url('cart.php')) ?>" name="remove_one" value="<?= (int) $l['id'] ?>"
                  onclick="this.form.action.value='remove';this.form.product_id.value='<?= (int) $l['id'] ?>';">Remove</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <input type="hidden" name="product_id" value="0">
    <p class="cta">
      <button class="btn primary" type="submit">Update cart</button>
      <button class="btn ghost" type="submit" onclick="this.form.action.value='clear';">Clear</button>
      <a class="btn primary" href="<?= e(url('checkout.php')) ?>">Checkout &rarr;</a>
    </p>
  </form>
  <h2>Subtotal: <?= e(format_money($subtotal)) ?></h2>
  <p class="result-meta">Delivery fee and discounts are calculated at checkout.</p>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
