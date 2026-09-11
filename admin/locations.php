<?php
/**
 * Oyejo Gas - location management: delivery zones/fees and time slots.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_once BASE_PATH . '/includes/delivery.php';

$me = current_user();
$can_zones = has_permission('zones.manage');
$can_slots = has_permission('slots.manage');
if (!$can_zones && !$can_slots) {
    http_response_code(403);
    $page_title = 'Forbidden';
    require BASE_PATH . '/includes/header.php';
    echo '<section class="stub"><h1>Forbidden</h1><p>You do not have location access.</p></section>';
    require BASE_PATH . '/includes/footer.php';
    exit;
}
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'zones');
if (!in_array($tab, ['zones', 'slots', 'suggestions'], true)) {
    $tab = 'zones';
}
if ($tab === 'suggestions' && !$can_zones) {
    $tab = 'zones';
}
if ($tab === 'zones' && !$can_zones) {
    $tab = 'slots';
}
if ($tab === 'slots' && !$can_slots) {
    $tab = 'zones';
}
$message = '';
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) post('action', '');
        $need_zones = in_array($action, ['zone_save', 'zone_delete', 'loc_approve', 'loc_reject'], true);
        if (($need_zones && !$can_zones) || (!$need_zones && $action !== '' && !$can_slots)) {
            $errors[] = 'You do not have permission for that action.';
        } elseif ($action === 'zone_save') {
            [$ok, $msg] = del_zone_save((int) post('item_id', 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'zone_delete') {
            [$ok, $msg] = del_zone_delete((int) post('item_id', 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'slot_save') {
            [$ok, $msg] = del_slot_save((int) post('item_id', 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'slot_delete') {
            [$ok, $msg] = del_slot_delete((int) post('item_id', 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'loc_approve') {
            [$ok, $msg] = loc_decide((int) post('item_id', 0), true, (int) $me['id'], post('note', ''), post('fee', 0));
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'loc_reject') {
            [$ok, $msg] = loc_decide((int) post('item_id', 0), false, (int) $me['id'], post('note', ''));
            $ok ? $message = $msg : $errors[] = $msg;
        }
    }
}

$edit = null;
$edit_id = (int) ($_GET['edit'] ?? 0);
if ($edit_id > 0 && in_array($tab, ['zones', 'slots'], true)) {
    $t = $tab === 'zones' ? 'delivery_zones' : 'delivery_slots';
    $s = db()->prepare('SELECT * FROM `' . $t . '` WHERE `id` = ?');
    $s->execute([$edit_id]);
    $edit = $s->fetch() ?: null;
}
$zones = $can_zones ? del_zones(false) : [];
$slots = $can_slots ? del_slots(false) : [];

$page_title = 'Locations';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Locations</p>
<h1>Locations</h1>
<p>
  <?php if ($can_zones) : ?><a class="btn<?= $tab === 'zones' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=zones')) ?>">Zones</a><?php endif; ?>
  <?php if ($can_slots) : ?><a class="btn<?= $tab === 'slots' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=slots')) ?>">Time slots</a><?php endif; ?>
  <?php if ($can_zones) : ?><a class="btn<?= $tab === 'suggestions' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=suggestions')) ?>">Suggestions</a><?php endif; ?>
</p>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php if ($tab === 'zones') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Zone</th><th>Fee</th><th>Free above</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($zones as $z) : ?>
        <tr><td><?= e($z['name']) ?><br><span class="result-meta"><?= e((string) ($z['description'] ?? '')) ?></span></td>
          <td><?= e(format_money((int) $z['fee_minor'])) ?></td>
          <td><?= $z['free_above_minor'] !== null ? e(format_money((int) $z['free_above_minor'])) : '—' ?></td>
          <td><?= (int) $z['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a class="btn small ghost" href="<?= e(url('admin/locations.php?tab=zones&edit=' . (int) $z['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this zone?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="zones">
              <input type="hidden" name="action" value="zone_delete">
              <input type="hidden" name="item_id" value="<?= (int) $z['id'] ?>">
              <button class="btn small" type="submit">Delete</button>
            </form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit zone' : 'Add zone' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="zones">
    <input type="hidden" name="action" value="zone_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="100" required></label>
    <label>Description<input name="description" value="<?= e((string) ($edit['description'] ?? '')) ?>" maxlength="255"></label>
    <label>Delivery fee (₦)<input type="number" step="0.01" min="0" name="fee" value="<?= e($edit ? number_format((int) $edit['fee_minor'] / 100, 2, '.', '') : '') ?>" required></label>
    <label>Free delivery above (₦, blank = never)<input type="number" step="0.01" min="0" name="free_above" value="<?= e($edit && $edit['free_above_minor'] !== null ? number_format((int) $edit['free_above_minor'] / 100, 2, '.', '') : '') ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add zone' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/locations.php?tab=zones')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php else : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Slot</th><th>Window</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($slots as $s) : ?>
        <tr><td><?= e($s['name']) ?></td>
          <td><?= e(substr((string) $s['window_start'], 0, 5)) ?>–<?= e(substr((string) $s['window_end'], 0, 5)) ?></td>
          <td><?= (int) $s['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a class="btn small ghost" href="<?= e(url('admin/locations.php?tab=slots&edit=' . (int) $s['id'])) ?>">Edit</a>
            <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this slot?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="slots">
              <input type="hidden" name="action" value="slot_delete">
              <input type="hidden" name="item_id" value="<?= (int) $s['id'] ?>">
              <button class="btn small" type="submit">Delete</button>
            </form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit slot' : 'Add slot' ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="slots">
    <input type="hidden" name="action" value="slot_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="100" required></label>
    <label>Window start<input type="time" name="window_start" value="<?= e(substr((string) ($edit['window_start'] ?? ''), 0, 5)) ?>" required></label>
    <label>Window end<input type="time" name="window_end" value="<?= e(substr((string) ($edit['window_end'] ?? ''), 0, 5)) ?>" required></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>" min="0" max="9999"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add slot' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/locations.php?tab=slots')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
