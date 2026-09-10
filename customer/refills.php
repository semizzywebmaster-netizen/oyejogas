<?php
/**
 * Oyejo Gas - customer refill requests: book, history, detail, cancel (Phase 12).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('shop.order');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/refills.php';

if (!oyejo_feature('gas_refills')) {
    http_response_code(403);
    $page_title = 'Refills disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Refill requests are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$uid = (int) $me['id'];
$cid = cart_customer_id($uid);
$message = '';
$errors = [];
$view = (int) ($_GET['view'] ?? 0);

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'request') {
            list($row, $errs) = refill_request($cid, $uid, [
                'size_id' => post('size_id', 0), 'qty' => post('qty', 0),
                'details' => post('details', ''), 'fulfillment' => post('fulfillment', 'delivery'),
                'address_id' => post('address_id', 0), 'zone_id' => post('zone_id', 0),
                'slot_id' => post('slot_id', 0),
            ]);
            if ($row) {
                redirect(url('customer/refills.php?view=' . $row['id'] . '&created=1'));
            }
            $errors = array_merge($errors, $errs);
        } elseif ($action === 'cancel') {
            list($ok, $msg) = refill_cancel_customer(post('refill_id', 0), $cid);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$refill = null;
if ($view > 0) {
    $refill = refill_get($view);
    if (!$refill || (int) $refill['customer_id'] !== $cid) {
        http_response_code(404);
        $page_title = 'Refill not found';
        require BASE_PATH . '/includes/header.php';
        echo '<section class="stub"><h1>Refill not found</h1><p><a href="' . e(url('customer/refills.php')) . '">Back to refills</a></p></section>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
}

$sizes = refill_sizes();
$zones = cart_zones();
$slots = cart_slots();
$s = db()->prepare('SELECT * FROM `customer_addresses` WHERE `customer_id` = ? ORDER BY `is_default` DESC, `id`');
$s->execute([$cid]);
$addresses = $s->fetchAll();
$history = refill_for_customer($cid);

$page_title = $refill ? ('Refill ' . $refill['refill_number']) : 'Gas refills';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($refill) : ?>
  <?php if (isset($_GET['created'])) : ?><div class="alert alert-success">Refill request received. We will confirm shortly.</div><?php endif; ?>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('customer/refills.php')) ?>">Refills</a> &rsaquo; <?= e($refill['refill_number']) ?></p>
  <h1>Refill <?= e($refill['refill_number']) ?></h1>
  <p><span class="badge"><?= e(ucfirst($refill['status'])) ?></span>
    <span class="badge"><?= e(ucfirst($refill['fulfillment'])) ?></span></p>
  <div class="table-scroll">
    <table class="data">
      <tbody>
        <tr><th>Size</th><td><?= e($refill['size_name']) ?> × <?= (int) $refill['qty'] ?></td></tr>
        <tr><th>Price</th><td><strong><?= e(format_money($refill['price_minor'])) ?></strong></td></tr>
        <?php if ($refill['customer_cylinder_details']) : ?><tr><th>Cylinder</th><td><?= e($refill['customer_cylinder_details']) ?></td></tr><?php endif; ?>
        <?php if ($refill['fulfillment'] === 'delivery') : ?>
          <tr><th>Deliver to</th><td><?= e((string) $refill['address_line']) ?>, <?= e((string) $refill['city']) ?>
            <?php if ($refill['zone_name']) : ?><br><?= e($refill['zone_name']) ?><?php endif; ?>
            <?php if ($refill['slot_name']) : ?> · <?= e($refill['slot_name']) ?><?php endif; ?></td></tr>
        <?php else : ?>
          <tr><th>Pickup</th><td>Bring your cylinder to the depot; we will confirm readiness here.</td></tr>
        <?php endif; ?>
        <?php if ($refill['driver_name']) : ?><tr><th>Driver</th><td><?= e($refill['driver_name']) ?></td></tr><?php endif; ?>
        <tr><th>Requested</th><td><?= e($refill['created_at']) ?></td></tr>
        <?php if ($refill['completed_at']) : ?><tr><th>Completed</th><td><?= e($refill['completed_at']) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (in_array($refill['status'], ['requested', 'assigned'], true)) : ?>
    <div class="card">
      <h2>Cancel this refill</h2>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="refill_id" value="<?= (int) $refill['id'] ?>">
        <p><button class="btn ghost" type="submit">Cancel refill</button></p>
      </form>
    </div>
  <?php endif; ?>
<?php else : ?>
  <p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Refills</p>
  <h1>Gas refills</h1>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <div class="card">
    <h2>Book a refill</h2>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="request">
      <label>Cylinder size
        <select name="size_id" required>
          <?php foreach ($sizes as $z) : ?>
            <option value="<?= (int) $z['id'] ?>"<?= $z['unit_minor'] === null ? ' disabled' : '' ?>>
              <?= e($z['name']) ?> — <?= $z['unit_minor'] === null ? 'unavailable' : e(format_money($z['unit_minor'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quantity (1–20)<input type="number" name="qty" value="1" min="1" max="20" required></label>
      <label>Your cylinder details (brand, condition — optional)<input name="details" maxlength="500"></label>
      <label class="radio"><input type="radio" name="fulfillment" value="delivery" checked> Deliver filled cylinder(s)</label>
      <label class="radio"><input type="radio" name="fulfillment" value="pickup"> I will pick up from the depot</label>
      <?php if ($addresses) : ?>
        <label>Delivery address
          <select name="address_id">
            <?php foreach ($addresses as $a) : ?>
              <option value="<?= (int) $a['id'] ?>"><?= e($a['label'] . ': ' . $a['address_line'] . ', ' . $a['city']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php else : ?>
        <p class="result-meta">No saved address yet — <a href="<?= e(url('customer/addresses.php')) ?>">add one</a> for delivery, or choose depot pickup.</p>
      <?php endif; ?>
      <label>Delivery zone
        <select name="zone_id">
          <?php foreach ($zones as $z) : ?><option value="<?= (int) $z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Time slot
        <select name="slot_id">
          <?php foreach ($slots as $sl) : ?><option value="<?= (int) $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <p><button class="btn primary" type="submit">Request refill</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Refill history</h2>
    <?php if (!$history) : ?><p class="result-meta">No refills yet.</p>
    <?php else : ?>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Refill</th><th>Size</th><th>Qty</th><th>Price</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($history as $h) : ?>
              <tr>
                <td><?= e($h['refill_number']) ?></td><td><?= e($h['size_name']) ?></td>
                <td><?= (int) $h['qty'] ?></td><td><?= e(format_money($h['price_minor'])) ?></td>
                <td><?= e(ucfirst($h['status'])) ?></td>
                <td><a href="<?= e(url('customer/refills.php?view=' . $h['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
