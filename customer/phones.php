<?php
/**
 * Oyejo Gas - customer phone management (Phase 10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.customer');
require_once BASE_PATH . '/includes/cart.php';

$me = current_user();
$uid = (int) $me['id'];
$u = auth_db_user($uid);
$cid = cart_customer_id($uid);
$message = '';
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        $id = (int) post('id', 0);
        if ($action === 'add') {
            $phone = trim((string) post('phone', ''));
            if (!preg_match('/^[0-9+\s()\-]{7,20}$/', $phone)) {
                $errors[] = 'Phone number looks invalid.';
            } else {
                try {
                    $s = db()->prepare('SELECT COUNT(*) FROM `customer_phones` WHERE `customer_id` = ?');
                    $s->execute([$cid]);
                    $primary = $s->fetchColumn() == 0 ? 1 : 0;
                    db()->prepare('INSERT INTO `customer_phones` (`customer_id`, `phone`, `is_primary`) VALUES (?, ?, ?)')
                        ->execute([$cid, $phone, $primary]);
                    $message = 'Phone number added.';
                } catch (PDOException $e) {
                    $errors[] = 'That phone number is already on your list.';
                }
            }
        } elseif (in_array($action, ['delete', 'primary', 'account'], true) && $id > 0) {
            $s = db()->prepare('SELECT * FROM `customer_phones` WHERE `id` = ? AND `customer_id` = ? LIMIT 1');
            $s->execute([$id, $cid]);
            $row = $s->fetch();
            if (!$row) {
                $errors[] = 'Phone number not found.';
            } elseif ($action === 'delete') {
                db()->prepare('DELETE FROM `customer_phones` WHERE `id` = ? LIMIT 1')->execute([$id]);
                $message = 'Phone number removed.';
            } elseif ($action === 'primary') {
                db()->prepare('UPDATE `customer_phones` SET `is_primary` = 0 WHERE `customer_id` = ?')->execute([$cid]);
                db()->prepare('UPDATE `customer_phones` SET `is_primary` = 1 WHERE `id` = ?')->execute([$id]);
                $message = 'Primary phone updated.';
            } else {
                $s = db()->prepare('SELECT `id` FROM `users` WHERE `phone` = ? AND `id` <> ? LIMIT 1');
                $s->execute([$row['phone'], $uid]);
                if ($s->fetch()) {
                    $errors[] = 'That number belongs to another account.';
                } else {
                    db()->prepare('UPDATE `users` SET `phone` = ?, `phone_verified_at` = NULL WHERE `id` = ?')
                        ->execute([$row['phone'], $uid]);
                    $u = auth_db_user($uid);
                    $message = 'Account phone updated. Please verify it.';
                }
            }
        }
    }
}

$s = db()->prepare('SELECT * FROM `customer_phones` WHERE `customer_id` = ? ORDER BY `is_primary` DESC, `id`');
$s->execute([$cid]);
$list = $s->fetchAll();

$page_title = 'Phones';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Phones</p>
<h1>Phones</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<p>Account phone: <strong><?= e((string) ($u['phone'] ?? '—')) ?></strong>
  <?= !empty($u['phone']) && empty($u['phone_verified_at']) ? '<a href="' . e(url('customer/verify-phone.php')) . '">Verify now</a>' : '' ?>
  <?= !empty($u['phone_verified_at']) ? '<span class="stock ok">Verified</span>' : '' ?></p>
<?php if ($list) : ?>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Phone</th><th>Primary</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($list as $p) : ?>
          <tr>
            <td><?= e($p['phone']) ?></td>
            <td><?= $p['is_primary'] ? 'Yes' : 'No' ?></td>
            <td>
              <form method="post" action="" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <?php if (!$p['is_primary']) : ?>
                  <button class="btn ghost" type="submit" name="action" value="primary">Primary</button>
                <?php endif; ?>
                <?php if ($p['phone'] !== (string) ($u['phone'] ?? '')) : ?>
                  <button class="btn ghost" type="submit" name="action" value="account">Use for account</button>
                <?php endif; ?>
                <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this number?')">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else : ?>
  <div class="card"><p>No extra phone numbers yet.</p></div>
<?php endif; ?>
<div class="card">
  <h2>Add phone number</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label>Phone number<input name="phone" required maxlength="30"></label>
    <p><button class="btn primary" type="submit">Add</button></p>
  </form>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
