<?php
/**
 * Oyejo Gas - checkout (Phase 9). Authenticated or guest (toggle-gated);
 * every figure recomputed server-side at placement.
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

$logged = is_logged_in();
if (!$logged && !oyejo_feature('guest_checkout')) {
    $page_title = 'Login required';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><p class="pill">Checkout</p><h1>Login required</h1>'
        . '<p>Guest checkout is disabled. Please log in to place your order.</p>'
        . '<p class="cta"><a class="btn primary" href="' . e(url('customer/login.php?next=' . urlencode('checkout.php'))) . '">Log in</a></p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

list($lines, $notices) = cart_lines();
$subtotal = cart_subtotal($lines);
$zones = cart_zones();
$slots = cart_slots();
$methods = cart_payment_methods();
$saved = [];

$cu = $logged ? current_user() : null;
$uid = $logged ? (int) ($cu['id'] ?? $cu['user_id'] ?? 0) : 0;
if ($logged && $uid > 0) {
    $cid = cart_customer_id($uid);
    $s = db()->prepare('SELECT * FROM `customer_addresses` WHERE `customer_id` = ? ORDER BY `is_default` DESC, `id`');
    $s->execute([$cid]);
    $saved = $s->fetchAll();
}

$errors = [];
// Sticky form values.
$f = [
    'name' => '', 'email' => '', 'phone' => '', 'recipient' => '', 'line' => '',
    'city' => '', 'landmark' => '', 'zone_id' => '', 'slot_id' => '', 'coupon' => '',
    'method' => 'cod', 'notes' => '', 'use_address' => '0',
];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        foreach (array_keys($f) as $k) {
            if ($k !== 'use_address') {
                $f[$k] = trim((string) post($k, $f[$k]));
            }
        }
        $f['use_address'] = (string) post('use_address', '0');
        if (!$lines) {
            $errors[] = 'Your cart is empty.';
        }
        // Guest account creation (login-equivalent identity for the order).
        if (!$errors && !$logged) {
            if (strlen($f['name']) < 2 || strlen($f['name']) > 100) {
                $errors[] = 'Name must be 2–100 characters.';
            }
            if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
            }
            $pw = (string) post('password', '');
            $pw2 = (string) post('password_confirm', '');
            if (strlen($pw) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
                $errors[] = 'Password must include a letter and a number.';
            } elseif ($pw !== $pw2) {
                $errors[] = 'Passwords do not match.';
            }
            if (!$errors) {
                $s = db()->prepare('SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1');
                $s->execute([$f['email']]);
                if ($s->fetch()) {
                    $errors[] = 'That email already has an account. Please log in to check out.';
                }
            }
            if (!$errors) {
                list($new_uid, $err) = auth_register_customer($f['name'], $f['email'], $f['phone'], $pw);
                if (!$new_uid) {
                    $errors[] = $err;
                } else {
                    auth_send_verification(auth_db_user($new_uid));
                    $uid = $new_uid;
                }
            }
        }
        if (!$errors && $logged) {
            require_permission('shop.order');
        }
        if (!$errors) {
            $cid = cart_customer_id($uid);
            if ($cid <= 0) {
                $errors[] = 'Could not load your customer profile.';
            } else {
                list($order, $place_errs) = cart_place_order([
                    'user_id' => $logged ? $uid : $uid,
                    'customer_id' => $cid,
                    'use_address_id' => $logged ? (int) $f['use_address'] : 0,
                    'addr' => [
                        'recipient' => $f['recipient'] !== '' ? $f['recipient'] : ($logged ? $cu['name'] : $f['name']),
                        'phone' => $f['phone'], 'line' => $f['line'], 'city' => $f['city'],
                        'landmark' => $f['landmark'],
                    ],
                    'zone_id' => (int) $f['zone_id'], 'slot_id' => (int) $f['slot_id'],
                    'coupon' => $f['coupon'], 'method' => $f['method'], 'notes' => $f['notes'],
                ]);
                if ($order) {
                    $_SESSION[OYEJO_LAST_ORDER_KEY] = $order['id'];
                    notify_emit($cid, 'order_confirmation', $logged ? $cu['email'] : $f['email'], 'Order ' . $order['number'] . ' confirmed', 'Your order ' . $order['number'] . ' (' . format_money($order['total']) . ') is confirmed. Track it in My account.');
                    if (($order['method'] ?? '') === 'wallet') {
                        redirect(url('customer/orders.php?view=' . $order['id'] . '&placed=1'));
                    }
                    redirect(url('customer/payments.php?placed=' . $order['id']));
                }
                $errors = array_merge($errors, $place_errs);
            }
        }
    }
}

$page_title = 'Checkout';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-hero">
  <p class="pill">Checkout</p>
  <h1>Checkout</h1>
</div>
<?php foreach ($notices as $n) : ?>
  <div class="alert alert-info"><?= e($n) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $e) : ?>
  <div class="alert alert-error"><?= e($e) ?></div>
<?php endforeach; ?>

<?php if (!$lines) : ?>
  <div class="card"><p>Your cart is empty.</p><p><a class="btn primary" href="<?= e(url('shop.php')) ?>">Browse the shop</a></p></div>
<?php else : ?>
<div class="checkout-grid">
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <?php if (!$logged) : ?>
      <div class="card">
        <h2>Your account</h2>
        <p class="result-meta">We will create your account with this order. Already registered?
          <a href="<?= e(url('customer/login.php?next=' . urlencode('checkout.php'))) ?>">Log in</a>.</p>
        <label>Full name<input name="name" value="<?= e($f['name']) ?>" required maxlength="100"></label>
        <label>Email<input type="email" name="email" value="<?= e($f['email']) ?>" required maxlength="190"></label>
        <label>Password (min 8, letter + number)<input type="password" name="password" autocomplete="new-password" required></label>
        <label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" required></label>
      </div>
    <?php endif; ?>
    <div class="card">
      <h2>Delivery address</h2>
      <?php if ($saved) : ?>
        <label>Use a saved address
          <select name="use_address">
            <option value="0">— Enter a new address below —</option>
            <?php foreach ($saved as $a) : ?>
              <option value="<?= (int) $a['id'] ?>"<?= $f['use_address'] === (string) $a['id'] ? ' selected' : '' ?>>
                <?= e($a['label'] . ': ' . $a['address_line'] . ', ' . $a['city']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <label>Recipient name<input name="recipient" value="<?= e($f['recipient']) ?>" maxlength="150" placeholder="Defaults to account name"></label>
      <label>Delivery phone<input name="phone" value="<?= e($f['phone']) ?>" required maxlength="30"></label>
      <label>Street address<input name="line" value="<?= e($f['line']) ?>" required maxlength="255"></label>
      <label>City<input name="city" value="<?= e($f['city']) ?>" required maxlength="100"></label>
      <label>Landmark (optional)<input name="landmark" value="<?= e($f['landmark']) ?>" maxlength="255"></label>
    </div>
    <div class="card">
      <h2>Delivery options</h2>
      <label>Delivery zone
        <select name="zone_id" required>
          <option value="">— Choose a zone —</option>
          <?php foreach ($zones as $z) : ?>
            <option value="<?= (int) $z['id'] ?>"<?= $f['zone_id'] === (string) $z['id'] ? ' selected' : '' ?>>
              <?= e($z['name']) ?> — <?= e(format_money((int) $z['fee_minor'])) ?><?php if ((int) $z['free_above_minor'] > 0) : ?> (free above <?= e(format_money((int) $z['free_above_minor'])) ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Time slot
        <select name="slot_id" required>
          <option value="">— Choose a slot —</option>
          <?php foreach ($slots as $s) : ?>
            <option value="<?= (int) $s['id'] ?>"<?= $f['slot_id'] === (string) $s['id'] ? ' selected' : '' ?>>
              <?= e($s['name']) ?> (<?= e(substr($s['window_start'], 0, 5)) ?>–<?= e(substr($s['window_end'], 0, 5)) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Coupon code (optional)<input name="coupon" value="<?= e($f['coupon']) ?>" maxlength="40" placeholder="e.g. WELCOME10"></label>
      <label>Order notes (optional)<input name="notes" value="<?= e($f['notes']) ?>" maxlength="500"></label>
    </div>
    <div class="card">
      <h2>Payment method</h2>
      <?php foreach ($methods as $key => $m) : ?>
        <?php if (oyejo_feature($m['toggle'])) : ?>
          <label class="radio"><input type="radio" name="method" value="<?= $key ?>"<?= $f['method'] === $key ? ' checked' : '' ?>> <?= e($m['label']) ?></label>
        <?php endif; ?>
      <?php endforeach; ?>
      <p class="result-meta">Wallet pays instantly. Bank transfer and online payments are verified after checkout under My payments; cash is collected on delivery.</p>
    </div>
    <p><button class="btn primary" type="submit">Place order</button></p>
  </form>
  <aside>
    <div class="card">
      <h2>Order summary</h2>
      <ul>
        <?php foreach ($lines as $l) : ?>
          <li><?= (int) $l['qty'] ?> × <?= e($l['name']) ?> — <?= e(format_money($l['total'])) ?></li>
        <?php endforeach; ?>
      </ul>
      <p><strong>Subtotal: <?= e(format_money($subtotal)) ?></strong></p>
      <p class="result-meta">Final total (fee + discounts) is computed securely when you place the order.</p>
    </div>
  </aside>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
