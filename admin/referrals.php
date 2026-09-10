<?php
/**
 * Oyejo Gas - referral management desk: tracking, flags, rewards,
 * reversals and settings (Phase 21, RR-07/RR-12/RR-13).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('referrals.manage');
require_once BASE_PATH . '/includes/wallet.php';
require_once BASE_PATH . '/includes/referrals.php';

$me = current_user();
$message = '';
$errors = [];
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'referrals');
if (!in_array($tab, ['referrals', 'rewards', 'settings'], true)) {
    $tab = 'referrals';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'flag') {
            [$ok, $msg] = ref_flag((int) ($_POST['item_id'] ?? 0), (int) $me['id'], (string) ($_POST['note'] ?? ''));
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'unflag') {
            [$ok, $msg] = ref_unflag((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'qualify') {
            $message = ref_qualify_due() . ' referral(s) qualified.';
        } elseif ($action === 'expire') {
            $message = ref_expire_due() . ' pending reward(s) expired.';
        } elseif ($action === 'reverse') {
            [$ok, $msg] = ref_reverse((int) ($_POST['item_id'] ?? 0), (int) $me['id'], (string) ($_POST['reason'] ?? ''));
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'settings') {
            [$ok, $msg] = ref_settings_save($_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$f_status = (string) ($_GET['status'] ?? '');
$refs = ref_all($f_status);
$rewards = ref_rewards_all((string) ($_GET['rstatus'] ?? ''));
$reports = ref_reports();
$cfg = ref_settings();

$page_title = 'Referrals';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Referrals</p>
<h1>Referrals</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>
  <a class="btn<?= $tab === 'referrals' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/referrals.php?tab=referrals')) ?>">Referrals</a>
  <a class="btn<?= $tab === 'rewards' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/referrals.php?tab=rewards')) ?>">Rewards</a>
  <a class="btn<?= $tab === 'settings' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/referrals.php?tab=settings')) ?>">Settings</a>
</p>

<?php if ($tab === 'referrals') : ?>
<p>
  <?php foreach ($reports['by_status'] as $r) : ?><?= e(ucfirst($r['status'])) ?>: <strong><?= (int) $r['n'] ?></strong> &nbsp;<?php endforeach; ?>
</p>
<form method="get" action="<?= e(url('admin/referrals.php')) ?>" class="filter-row">
  <input type="hidden" name="tab" value="referrals">
  <label>Status
    <select name="status">
      <option value="">All</option>
      <?php foreach (['pending' => 'Pending', 'qualified' => 'Qualified', 'rewarded' => 'Rewarded', 'expired' => 'Expired', 'flagged' => 'Flagged'] as $k => $label) : ?>
        <option value="<?= e($k) ?>"<?= $f_status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p><button class="btn" type="submit">Filter</button></p>
</form>
<form method="post" action="<?= e(url('admin/referrals.php?tab=referrals')) ?>" class="filter-row">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="referrals">
  <p><button class="btn" type="submit" name="action" value="qualify">Qualify due now</button>
  <button class="btn" type="submit" name="action" value="expire">Expire due now</button></p>
</form>
<div class="card">
  <?php if (!$refs) : ?><p>No referrals yet.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Referrer</th><th>Referred</th><th>Code</th><th>Status</th><th>Flag</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($refs as $r) : ?>
        <tr><td><?= e($r['referrer_name']) ?><br><span class="result-meta"><?= e($r['referrer_code']) ?></span></td>
          <td><?= e($r['referred_name']) ?><br><span class="result-meta"><?= e($r['referred_code']) ?></span></td>
          <td><?= e((string) $r['code_used']) ?></td><td><?= e(ucfirst($r['status'])) ?></td>
          <td><?= e((string) ($r['flag_note'] ?? '—')) ?></td>
          <td>
            <?php if (in_array($r['status'], ['pending', 'qualified'], true)) : ?>
              <form method="post" action="<?= e(url('admin/referrals.php?tab=referrals')) ?>" class="inline-form" onsubmit="return confirm('Flag this referral?');">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="referrals">
                <input type="hidden" name="action" value="flag">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <input name="note" maxlength="255" placeholder="Note" required size="12">
                <button class="btn small ghost" type="submit">Flag</button>
              </form>
            <?php elseif ($r['status'] === 'flagged') : ?>
              <form method="post" action="<?= e(url('admin/referrals.php?tab=referrals')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="referrals">
                <input type="hidden" name="action" value="unflag">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit">Clear</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'rewards') : ?>
<div class="card">
  <?php if (!$rewards) : ?><p>No rewards yet.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Recipient</th><th>Kind</th><th>Amount</th><th>Status</th><th>Expires</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rewards as $r) : ?>
        <tr><td><?= e($r['customer_name']) ?></td><td><?= e(ucfirst($r['kind'])) ?></td>
          <td><?= e(format_money((int) $r['amount_minor'])) ?></td><td><?= e(ucfirst($r['status'])) ?></td>
          <td><?= e(substr((string) ($r['expires_at'] ?? '—'), 0, 16)) ?></td>
          <td>
            <?php if ($r['status'] === 'credited') : ?>
              <form method="post" action="<?= e(url('admin/referrals.php?tab=rewards')) ?>" class="inline-form" onsubmit="return confirm('Reverse this reward?');">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="rewards">
                <input type="hidden" name="action" value="reverse">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <input name="reason" maxlength="255" placeholder="Reason" required size="12">
                <button class="btn small ghost" type="submit">Reverse</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'settings') : ?>
<div class="card">
  <h2>Referral settings</h2>
  <form method="post" action="<?= e(url('admin/referrals.php?tab=settings')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="settings">
    <input type="hidden" name="action" value="settings">
    <label>Referrer reward (₦)<input name="referral_reward_referrer_minor" inputmode="decimal" value="<?= e((string) ($cfg['referral_reward_referrer_minor'] / 100)) ?>" required></label>
    <label>Referred reward (₦)<input name="referral_reward_referred_minor" inputmode="decimal" value="<?= e((string) ($cfg['referral_reward_referred_minor'] / 100)) ?>" required></label>
    <label>Minimum purchase (₦)<input name="referral_min_purchase_minor" inputmode="decimal" value="<?= e((string) ($cfg['referral_min_purchase_minor'] / 100)) ?>" required></label>
    <label>Reward expiry (days)<input type="number" name="referral_reward_expiry_days" value="<?= (int) $cfg['referral_reward_expiry_days'] ?>" min="1" max="365" required></label>
    <label>Velocity flag (referrals per 24h)<input type="number" name="referral_velocity_24h" value="<?= (int) $cfg['referral_velocity_24h'] ?>" min="1" max="1000" required></label>
    <p><button class="btn primary" type="submit">Save settings</button></p>
  </form>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
