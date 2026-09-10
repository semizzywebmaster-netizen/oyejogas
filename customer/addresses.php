<?php
/**
 * Oyejo Gas - customer address book (Phase 10). Orders keep a text snapshot,
 * so addresses stay freely editable here.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$message = '';
$errors = [];
$edit = null;

function addr_validate(array $a) {
    $e = [];
    if (strlen(trim($a['label'])) < 1 || strlen($a['label']) > 50) {
        $e[] = 'Label must be 1–50 characters.';
    }
    if (strlen(trim($a['recipient'])) < 2 || strlen($a['recipient']) > 150) {
        $e[] = 'Recipient name must be 2–150 characters.';
    }
    if (!preg_match('/^[0-9+\s()\-]{7,20}$/', trim($a['phone']))) {
        $e[] = 'Phone number looks invalid.';
    }
    if (strlen(trim($a['line'])) < 5 || strlen($a['line']) > 255) {
        $e[] = 'Street address must be 5–255 characters.';
    }
    if (strlen(trim($a['city'])) < 2 || strlen($a['city']) > 100) {
        $e[] = 'City must be 2–100 characters.';
    }
    return $e;
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        $id = (int) post('id', 0);
        if ($action === 'default' && $id > 0) {
            $s = db()->prepare('SELECT `id` FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
            $s->execute([$id, $cid]);
            if ($s->fetch()) {
                db()->prepare('UPDATE `customer_addresses` SET `is_default` = 0 WHERE `customer_id` = ?')->execute([$cid]);
                db()->prepare('UPDATE `customer_addresses` SET `is_default` = 1 WHERE `id` = ?')->execute([$id]);
                $message = 'Default address updated.';
            } else {
                $errors[] = 'Address not found.';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $s = db()->prepare('DELETE FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
            $s->execute([$id, $cid]);
            $message = $s->rowCount() ? 'Address deleted.' : 'Address not found.';
            if (!$s->rowCount()) {
                $errors[] = $message;
                $message = '';
            }
        } elseif ($action === 'save') {
            $a = [
                'label' => trim((string) post('label', 'Home')),
                'recipient' => trim((string) post('recipient', '')),
                'phone' => trim((string) post('phone', '')),
                'line' => trim((string) post('line', '')),
                'city' => trim((string) post('city', '')),
                'landmark' => substr(trim((string) post('landmark', '')), 0, 255) ?: null,
                'zone_id' => (int) post('zone_id', 0) ?: null,
            ];
            $errors = addr_validate($a);
            if ($a['zone_id']) {
                $s = db()->prepare('SELECT `id` FROM `delivery_zones` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
                $s->execute([$a['zone_id']]);
                if (!$s->fetch()) {
                    $errors[] = 'Choose a valid delivery zone.';
                }
            }
            if (!$errors) {
                if ($id > 0) {
                    $s = db()->prepare(
                        'UPDATE `customer_addresses` SET `label` = ?, `recipient_name` = ?, `phone` = ?,'
                        . ' `address_line` = ?, `city` = ?, `landmark` = ?, `zone_id` = ?'
                        . ' WHERE `id` = ? AND `customer_id` = ? LIMIT 1'
                    );
                    $s->execute([$a['label'], $a['recipient'], $a['phone'], $a['line'], $a['city'], $a['landmark'], $a['zone_id'], $id, $cid]);
                    $message = $s->rowCount() ? 'Address updated.' : 'Address not found.';
                    if (!$s->rowCount()) {
                        $errors[] = $message;
                        $message = '';
                    }
                } else {
                    $s = db()->prepare('SELECT COUNT(*) FROM `customer_addresses` WHERE `customer_id` = ?');
                    $s->execute([$cid]);
                    $is_default = $s->fetchColumn() == 0 ? 1 : 0;
                    db()->prepare(
                        'INSERT INTO `customer_addresses` (`customer_id`, `label`, `recipient_name`, `phone`,'
                        . ' `address_line`, `city`, `state`, `landmark`, `zone_id`, `is_default`)'
                        . ' VALUES (?, ?, ?, ?, ?, ?, \'Lagos\', ?, ?, ?)'
                    )->execute([$cid, $a['label'], $a['recipient'], $a['phone'], $a['line'], $a['city'], $a['landmark'], $a['zone_id'], $is_default]);
                    $message = 'Address added.';
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
    $s->execute([(int) $_GET['edit'], $cid]);
    $edit = $s->fetch() ?: null;
}

$s = db()->prepare(
    'SELECT `a`.*, `z`.`name` AS `zone_name` FROM `customer_addresses` `a`'
    . ' LEFT JOIN `delivery_zones` `z` ON `z`.`id` = `a`.`zone_id`'
    . ' WHERE `a`.`customer_id` = ? ORDER BY `a`.`is_default` DESC, `a`.`id`'
);
$s->execute([$cid]);
$list = $s->fetchAll();
$zones = cart_zones();

$page_title = 'Addresses';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Addresses</p>
<h1>Addresses</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<?php if (!$list) : ?>
  <div class="card"><p>No saved addresses yet.</p></div>
<?php else : ?>
  <div class="grid">
    <?php foreach ($list as $a) : ?>
      <article class="card">
        <h3><?= e($a['label']) ?> <?= $a['is_default'] ? '<span class="stock ok">Default</span>' : '' ?></h3>
        <p><?= e($a['recipient_name']) ?><br><?= e($a['address_line']) ?><br><?= e($a['city']) ?>, <?= e($a['state']) ?><br><?= e($a['phone']) ?>
          <?php if ($a['zone_name']) : ?><br><span class="result-meta"><?= e($a['zone_name']) ?></span><?php endif; ?></p>
        <form method="post" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <p class="cta">
            <a class="btn ghost" href="<?= e(url('customer/addresses.php?edit=' . $a['id'])) ?>">Edit</a>
            <?php if (!$a['is_default']) : ?>
              <button class="btn ghost" type="submit" name="action" value="default">Make default</button>
            <?php endif; ?>
            <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Delete this address?')">Delete</button>
          </p>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<div class="card">
  <h2><?= $edit ? 'Edit address' : 'Add address' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
    <label>Label<input name="label" value="<?= e($edit['label'] ?? 'Home') ?>" required maxlength="50"></label>
    <label>Recipient name<input name="recipient" value="<?= e($edit['recipient_name'] ?? '') ?>" required maxlength="150"></label>
    <label>Phone<input name="phone" value="<?= e($edit['phone'] ?? '') ?>" required maxlength="30"></label>
    <label>Street address<input name="line" value="<?= e($edit['address_line'] ?? '') ?>" required maxlength="255"></label>
    <label>City<input name="city" value="<?= e($edit['city'] ?? '') ?>" required maxlength="100"></label>
    <label>Landmark (optional)<input name="landmark" value="<?= e((string) ($edit['landmark'] ?? '')) ?>" maxlength="255"></label>
    <label>Delivery zone (optional)
      <select name="zone_id">
        <option value="0">— None —</option>
        <?php foreach ($zones as $z) : ?>
          <option value="<?= (int) $z['id'] ?>"<?= $edit && (int) ($edit['zone_id'] ?? 0) === (int) $z['id'] ? ' selected' : '' ?>><?= e($z['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add address' ?></button></p>
  </form>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
