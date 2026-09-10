<?php
/**
 * Oyejo Gas - one-click reorder (Phase 10). POST-only: re-adds an owned
 * order's lines to the cart (live availability rules apply), then goes to cart.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('shop.order');
require_once BASE_PATH . '/includes/cart.php';

if (request_method() !== 'POST' || !csrf_verify(post('csrf_token'))) {
    http_response_code(403);
    $page_title = 'Reorder';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Reorder needs the button on your order page.</h1>'
        . '<p><a href="' . e(url('customer/orders.php')) . '">Back to orders</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$id = (int) post('order_id', 0);
$s = db()->prepare('SELECT `id` FROM `orders` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
$s->execute([$id, $cid]);
if (!$s->fetch()) {
    redirect(url('customer/orders.php'));
}
$s = db()->prepare('SELECT `product_id`, `qty` FROM `order_items` WHERE `order_id` = ?');
$s->execute([$id]);
$added = 0;
foreach ($s->fetchAll() as $it) {
    list($ok, $msg) = cart_add($it['product_id'], $it['qty']);
    if ($ok) {
        $added++;
    }
}
redirect(url('cart.php?reordered=' . $added));
