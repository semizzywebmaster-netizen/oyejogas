<?php
/**
 * Oyejo Gas - customer cylinder pickup / exchange / return (Phase 13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('shop.order');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/pickups.php';

if (!oyejo_feature('cylinder_pickups')) {
    http_response_code(403);
    $page_title = 'Pickups disabled';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Pickup requests are currently disabled.</h1><p>Please check back later.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}

$me = current_user();
$uid = (int) $me['id'];
$cid = cart_customer_id($uid);
$message = '';
$errors = [];
$view = (int) ($_GET['view'] ?? 0);
$types = pickup_types();

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'request') {
            list($row, $errs) = pickup_request($cid, $uid, [
                'type' => post('type', ''), 'size_id' => post('size_id', 0),
                'qty' => post('qty', 0), 'serials' => post('serials', ''),
                'address_id' => post('address_id', 0), 'slot_id' => post('slot_id', 0),
                'scheduled_date' => post('scheduled_date', ''), 'notes' => post('notes', ''),
            ]);
            if ($row) {
                redirect(url('customer/pickups.php?view=' . $row['id'] . '&created=1'));
            }
            $errors = array_merge($errors, $errs);
        } elseif ($action === 'cancel') {
            list($ok, $msg) = pickup_cancel(post('pickup_id', 0), $cid, false);
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$pickup = null;
if ($view > 0) {
    $pickup = pickup_get($view);
    if (!$pickup || (int) $pickup['customer_id'] !== $cid) {
        http_response_code(404);
        $page_title = 'Pickup not found';
        require BASE_PATH . '/includes/header.php';
        echo '<section class="stub"><h1>Pickup not found</h1><p><a href="' . e(url('customer/pickups.php')) . '">Back to pickups</a></p></section>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
}

$sizes = db()->query('SELECT * FROM `cylinder_sizes` WHERE `is_active` = 1 ORDER BY `sort_order`')->fetchAll();
$slots = cart_slots();
$s = db()->prepare('SELECT * FROM `customer_addresses` WHERE `customer_id` = ? ORDER BY `is_default` DESC, `id`');
$s->execute([$cid]);
$addresses = $s->fetchAll();
$history = pickup_for_customer($cid);
$held = cylinders_held($cid);

$page_title = $pickup ? ('Pickup ' . $pickup['pickup_number']) : 'Cylinder pickup & exchange';
require BASE_PATH . '/includes/header.php';
?>
<?php if ($pickup) : ?>
  <?php if (isset($_GET['created'])) : ?><div class="alert alert-success">Request received. We will confirm your collection date.</div><?php endif; ?>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <p class="crumbs"><a href="<?= e(url('customer/pickups.php')) ?>">Pickups</a> &rsaquo; <?= e($pickup['pickup_number']) ?></p>
  <h1>Pickup <?= e($pickup['pickup_number']) ?></h1>
  <p><span class="badge"><?= e($types[$pickup['type']] ?? $pickup['type']) ?></span>
    <span class="badge"><?= e(ucfirst($pickup['status'])) ?></span></p>
  <div class="table-scroll">
    <table class="data">
      <tbody>
        <tr><th>Size / qty</th><td><?= e((string) $pickup['size_name']) ?> × <?= (int) $pickup['qty'] ?></td></tr>
        <?php if ((int) $pickup['deposit_minor'] > 0) : ?><tr><th>Deposit due</th><td><?= e(format_money($pickup['deposit_minor'])) ?> (collected on delivery)</td></tr><?php endif; ?>
        <tr><th>Collect from</th><td><?= e((string) $pickup['address_line']) ?>, <?= e((string) $pickup['city']) ?></td></tr>
        <tr><th>Date / slot</th><td><?= e((string) $pickup['scheduled_date']) ?> · <?= e((string) $pickup['slot_name']) ?></td></tr>
        <?php if ($pickup['driver_name']) : ?><tr><th>Driver</th><td><?= e($pickup['driver_name']) ?></td></tr><?php endif; ?>
        <?php if ($pickup['notes']) : ?><tr><th>Notes</th><td><?= nl2br(e($pickup['notes'])) ?></td></tr><?php endif; ?>
        <?php if ($pickup['completed_at']) : ?><tr><th>Completed</th><td><?= e($pickup['completed_at']) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pickup['cylinders']) : ?>
    <div class="card">
      <h2>Cylinders</h2>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Serial</th><th>Direction</th><th>Condition</th><th>Ownership</th></tr></thead>
          <tbody>
            <?php foreach ($pickup['cylinders'] as $c) : ?>
              <tr><td><?= e((string) $c['serial_snapshot']) ?></td><td><?= e(ucfirst($c['direction'])) ?></td>
                <td><?= e((string) ($c['condition_on_collect'] ?: '—')) ?></td><td><?= e(ucfirst((string) ($c['ownership'] ?: '—'))) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  <?php if (in_array($pickup['status'], ['requested', 'scheduled'], true)) : ?>
    <div class="card">
      <h2>Cancel this pickup</h2>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="pickup_id" value="<?= (int) $pickup['id'] ?>">
        <p><button class="btn ghost" type="submit">Cancel pickup</button></p>
      </form>
    </div>
  <?php endif; ?>
<?php else : ?>
  <p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Pickups</p>
  <h1>Cylinder pickup &amp; exchange</h1>
  <?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
  <div class="card">
    <h2>Book a collection</h2>
    <?php if (!$addresses) : ?>
      <p class="result-meta"><a href="<?= e(url('customer/addresses.php')) ?>">Add an address</a> first — we need to know where to collect.</p>
    <?php else : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request">
        <label>Type
          <select name="type">
            <?php foreach ($types as $k => $v) : ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Cylinder size
          <select name="size_id">
            <?php foreach ($sizes as $z) : ?><option value="<?= (int) $z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Quantity (1–20)<input type="number" name="qty" value="1" min="1" max="20" required></label>
        <label>Your cylinder serials (optional, separated by spaces/commas)<input name="serials" maxlength="500"></label>
        <label>Collect from
          <select name="address_id">
            <?php foreach ($addresses as $a) : ?>
              <option value="<?= (int) $a['id'] ?>"><?= e($a['label'] . ': ' . $a['address_line'] . ', ' . $a['city']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Preferred date<input type="date" name="scheduled_date" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Time slot
          <select name="slot_id">
            <?php foreach ($slots as $sl) : ?><option value="<?= (int) $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Notes (optional)<input name="notes" maxlength="2000"></label>
        <p><button class="btn primary" type="submit">Request pickup</button></p>
      </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>My cylinders</h2>
    <?php if (!$held) : ?><p class="result-meta">No cylinders in your hold yet. Exchanged full cylinders appear here.</p>
    <?php else : ?>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Serial</th><th>Size</th><th>Ownership</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($held as $h) : ?>
              <tr><td><?= e($h['serial']) ?></td><td><?= e($h['size_name']) ?></td>
                <td><?= e(ucfirst($h['ownership'])) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $h['status']))) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Pickup history</h2>
    <?php if (!$history) : ?><p class="result-meta">No pickups yet.</p>
    <?php else : ?>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Pickup</th><th>Type</th><th>Size</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($history as $h) : ?>
              <tr>
                <td><?= e($h['pickup_number']) ?></td><td><?= e($types[$h['type']] ?? $h['type']) ?></td>
                <td><?= e((string) $h['size_name']) ?></td><td><?= e(ucfirst($h['status'])) ?></td>
                <td><a href="<?= e(url('customer/pickups.php?view=' . $h['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
