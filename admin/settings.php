<?php
/**
 * Oyejo Gas - website / payment / notification settings + feature
 * toggles desk (Phase 15, AD-15…AD-18). Payment secrets are never stored
 * here — they live in server environment / protected config only.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('settings.view');
require_once BASE_PATH . '/includes/admin.php';

$me = current_user();
$can_edit = has_permission('settings.edit');
$can_toggles = has_permission('toggles.manage');
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_group') {
            if (!$can_edit) {
                $errors[] = 'You do not have permission to edit settings.';
            } else {
                [$ok, $msg] = adm_settings_save($_POST['group'] ?? '', $_POST, (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        } elseif ($action === 'toggle') {
            if (!$can_toggles) {
                $errors[] = 'You do not have permission to manage toggles.';
            } else {
                [$ok, $msg] = adm_toggle_set($_POST['key'] ?? '', isset($_POST['enabled']), (int) $me['id']);
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}

[$groups, $all] = adm_settings('');
$toggles = adm_toggles();
$group_labels = [
    'site' => 'Website', 'contact' => 'Contact', 'locale' => 'Locale & currency',
    'orders' => 'Orders', 'wallet' => 'Wallet limits', 'payment' => 'Payments (no secrets here)',
    'notifications' => 'Notifications', 'appearance' => 'Appearance',
];
$naira_minor = ['min_order_minor', 'wallet_topup_min_minor', 'wallet_topup_max_minor',
    'wallet_balance_cap_minor', 'wallet_daily_topup_max_minor'];

$page_title = 'Settings';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Settings</p>
<h1>Settings &amp; toggles</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<?php foreach ($groups as $group => $keys) : ?>
<div class="card">
  <h2><?= e($group_labels[$group] ?? ucfirst($group)) ?></h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_group">
    <input type="hidden" name="group" value="<?= e($group) ?>">
    <?php foreach ($keys as $k) : ?>
      <?php $val = $all[$k] ?? ''; ?>
      <label><?= e(ucwords(str_replace('_', ' ', preg_replace('/_minor$/', ' (₦)', $k)))) ?>
        <input name="<?= e($k) ?>" value="<?= e(in_array($k, $naira_minor, true) && is_numeric($val) ? (string) ($val / 100) : (string) $val) ?>"<?= $can_edit ? '' : ' readonly' ?>>
      </label>
    <?php endforeach; ?>
    <?php if ($can_edit) : ?><p><button class="btn small primary" type="submit">Save <?= e($group_labels[$group] ?? $group) ?></button></p><?php endif; ?>
  </form>
</div>
<?php endforeach; ?>

<div class="card">
  <h2>Feature toggles</h2>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Feature</th><th>Status</th><?php if ($can_toggles) : ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($toggles as $t) : ?>
        <tr><td><strong><?= e($t['label']) ?></strong><br><span class="result-meta"><?= e((string) ($t['description'] ?: $t['key'])) ?></span></td>
          <td><?= (int) $t['enabled'] ? 'Enabled' : 'Disabled' ?></td>
          <?php if ($can_toggles) : ?><td>
            <form method="post" action="" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="key" value="<?= e($t['key']) ?>">
              <?php if (!(int) $t['enabled']) : ?><input type="hidden" name="enabled" value="1"><?php endif; ?>
              <button class="btn small<?= (int) $t['enabled'] ? ' ghost' : ' primary' ?>" type="submit"><?= (int) $t['enabled'] ? 'Disable' : 'Enable' ?></button>
            </form>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
