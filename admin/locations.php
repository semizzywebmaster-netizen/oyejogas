<?php
/**
 * Oyejo Gas - location management: delivery zones/fees, time slots and customer suggestions.
 * Admin approves suggestions before they go live as delivery zones.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_once BASE_PATH . '/includes/delivery.php';
require_once BASE_PATH . '/includes/growth.php';

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
$suggest_filter = (string) ($_GET['status'] ?? 'pending');
if (!in_array($suggest_filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $suggest_filter = 'pending';
}

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
$suggestions = [];
if ($tab === 'suggestions' && $can_zones) {
    try {
        $status = $suggest_filter === 'all' ? '' : $suggest_filter;
        $suggestions = loc_suggestions($status !== '' ? $status : 'pending', 200);
        if ($status === '' ) {
            // fetch all statuses when filter is all
            $suggestions = array_merge(
                loc_suggestions('pending', 200),
                loc_suggestions('approved', 200),
                loc_suggestions('rejected', 200)
            );
            usort($suggestions, function($a,$b){ return $b['id'] <=> $a['id']; });
        }
    } catch (Throwable $t) {
        $errors[] = 'Could not load suggestions: ' . $t->getMessage();
        $suggestions = [];
    }
}

$page_title = 'Locations';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Locations</p>
<h1>Locations</h1>
<p>
  <?php if ($can_zones) : ?><a class="btn<?= $tab === 'zones' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=zones')) ?>">Zones</a><?php endif; ?>
  <?php if ($can_slots) : ?><a class="btn<?= $tab === 'slots' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=slots')) ?>">Time slots</a><?php endif; ?>
  <?php if ($can_zones) : ?><a class="btn<?= $tab === 'suggestions' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/locations.php?tab=suggestions')) ?>">Suggestions<?php
    try { $pc = count(loc_suggestions('pending', 200)); if ($pc>0) echo ' ('.$pc.')'; } catch(Throwable $t) {}
  ?></a><?php endif; ?>
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

<?php elseif ($tab === 'slots') : ?>
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

<?php elseif ($tab === 'suggestions') : ?>
<div class="card">
  <h2>Location suggestions</h2>
  <p class="result-meta">Customers can suggest new delivery areas. Approve to create a live zone, or reject.</p>
  <p>
    <a class="btn<?= $suggest_filter === 'pending' ? ' primary' : ' ghost' ?> small" href="<?= e(url('admin/locations.php?tab=suggestions&status=pending')) ?>">Pending</a>
    <a class="btn<?= $suggest_filter === 'approved' ? ' primary' : ' ghost' ?> small" href="<?= e(url('admin/locations.php?tab=suggestions&status=approved')) ?>">Approved</a>
    <a class="btn<?= $suggest_filter === 'rejected' ? ' primary' : ' ghost' ?> small" href="<?= e(url('admin/locations.php?tab=suggestions&status=rejected')) ?>">Rejected</a>
    <a class="btn<?= $suggest_filter === 'all' ? ' primary' : ' ghost' ?> small" href="<?= e(url('admin/locations.php?tab=suggestions&status=all')) ?>">All</a>
  </p>
  <?php if (!$suggestions) : ?>
    <p class="result-meta">No <?= e($suggest_filter) ?> suggestions.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Location</th><th>Customer</th><th>City</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($suggestions as $sg) : ?>
        <tr>
          <td><strong><?= e($sg['name']) ?></strong><?php if (!empty($sg['description'])) : ?><br><span class="result-meta"><?= e($sg['description']) ?></span><?php endif; ?></td>
          <td><?= e($sg['customer_name'] ?? '') ?><br><span class="result-meta"><?= e($sg['email'] ?? '') ?></span></td>
          <td><?= e($sg['city']) ?></td>
          <td><?= e(ucfirst($sg['status'])) ?><?php if (!empty($sg['zone_id'])) : ?><br><span class="result-meta">Zone #<?= (int) $sg['zone_id'] ?></span><?php endif; ?></td>
          <td><?= e(substr((string) $sg['created_at'], 0, 16)) ?><?php if (!empty($sg['review_note'])) : ?><br><span class="result-meta"><?= e($sg['review_note']) ?></span><?php endif; ?></td>
          <td>
            <?php if ($sg['status'] === 'pending') : ?>
              <form method="post" action="" class="stack" style="min-width:220px">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="suggestions">
                <input type="hidden" name="action" value="loc_approve">
                <input type="hidden" name="item_id" value="<?= (int) $sg['id'] ?>">
                <label>Fee (₦)<input type="number" step="0.01" min="0" name="fee" value="1200" required></label>
                <label>Note (optional)<input name="note" maxlength="255" placeholder="e.g. Approved for trial"></label>
                <p><button class="btn small primary" type="submit">Approve & go live</button></p>
              </form>
              <form method="post" action="" class="stack" style="margin-top:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="suggestions">
                <input type="hidden" name="action" value="loc_reject">
                <input type="hidden" name="item_id" value="<?= (int) $sg['id'] ?>">
                <label>Reason (optional)<input name="note" maxlength="255" placeholder="e.g. Out of coverage"></label>
                <p><button class="btn small ghost" type="submit" onclick="return confirm('Reject this suggestion?')">Reject</button></p>
              </form>
            <?php else : ?>
              <span class="result-meta"><?= $sg['status'] === 'approved' ? 'Live as zone' : 'Rejected' ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
