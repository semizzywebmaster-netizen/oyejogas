<?php
/**
 * Oyejo Gas - spin-to-win desk: campaigns, prizes, spins, reversals
 * (Phase 20, SP-01/SP-02/SP-10/SP-11/SP-12).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('spin.manage');
require_once BASE_PATH . '/includes/wallet.php';
require_once BASE_PATH . '/includes/marketing.php';
require_once BASE_PATH . '/includes/spin.php';

$me = current_user();
$message = '';
$errors = [];
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'campaigns');
if (!in_array($tab, ['campaigns', 'prizes', 'spins', 'reports'], true)) {
    $tab = 'campaigns';
}
$camp_id = (int) ($_GET['campaign'] ?? $_POST['campaign'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'campaign_save') {
            [$ok, $msg] = spin_campaign_save((int) ($_POST['item_id'] ?? 0), $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'campaign_delete') {
            [$ok, $msg] = spin_campaign_delete((int) ($_POST['item_id'] ?? 0), (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'prize_save') {
            [$ok, $msg] = spin_prize_save((int) ($_POST['item_id'] ?? 0), $camp_id, $_POST, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'prize_delete') {
            [$ok, $msg] = spin_prize_delete((int) ($_POST['item_id'] ?? 0), $camp_id, (int) $me['id']);
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'reverse') {
            [$ok, $msg] = spin_reverse((int) ($_POST['item_id'] ?? 0), (int) $me['id'], (string) ($_POST['reason'] ?? ''));
            $ok ? $message = $msg : $errors[] = $msg;
        } elseif ($action === 'expire') {
            $n = spin_expire_due();
            $message = $n . ' pending reward(s) expired.';
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$campaigns = spin_campaigns_all();
$camp = $camp_id > 0 ? spin_campaign_get($camp_id) : null;
$prizes = $camp ? spin_prizes($camp['id']) : [];
$f_status = (string) ($_GET['status'] ?? '');
$f_campaign = (int) ($_GET['f_campaign'] ?? 0);
$spins = spin_all($f_campaign, $f_status);
$reports = spin_reports();
$rules = spin_rules();
$types = spin_reward_types();

$edit = null;
if (isset($_GET['edit'])) {
    $pool = $tab === 'prizes' ? $prizes : $campaigns;
    foreach ($pool as $r) {
        if ((int) $r['id'] === (int) $_GET['edit']) {
            $edit = $r;
        }
    }
}

$page_title = 'Spin-to-win';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Spin-to-win</p>
<h1>Spin-to-win</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<p>
  <a class="btn<?= $tab === 'campaigns' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/spin.php?tab=campaigns')) ?>">Campaigns</a>
  <a class="btn<?= $tab === 'prizes' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/spin.php?tab=prizes&campaign=' . $camp_id)) ?>">Prizes</a>
  <a class="btn<?= $tab === 'spins' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/spin.php?tab=spins')) ?>">Spins</a>
  <a class="btn<?= $tab === 'reports' ? ' primary' : ' ghost' ?>" href="<?= e(url('admin/spin.php?tab=reports')) ?>">Reports</a>
</p>

<?php if ($tab === 'campaigns') : ?>
<div class="card">
  <div class="table-scroll"><table class="data">
    <thead><tr><th>Name</th><th>Window</th><th>Period</th><th>Prizes</th><th>Spins</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($campaigns as $r) : ?>
        <tr><td><strong><?= e($r['name']) ?></strong><br><span class="result-meta"><?= e($r['slug']) ?></span></td>
          <td><?= e(substr($r['starts_at'], 0, 16)) ?> → <?= e(substr($r['ends_at'], 0, 16)) ?></td>
          <td><?= e($rules[$r['period_rule']]) ?></td>
          <td><?= (int) $r['prizes'] ?></td><td><?= (int) $r['spins'] ?></td>
          <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
          <td><a href="<?= e(url('admin/spin.php?tab=prizes&campaign=' . (int) $r['id'])) ?>">Prizes</a>
            <a href="<?= e(url('admin/spin.php?tab=campaigns&edit=' . (int) $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url('admin/spin.php?tab=campaigns')) ?>" class="inline-form" onsubmit="return confirm('Delete this campaign?');">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="campaigns">
              <input type="hidden" name="action" value="campaign_delete">
              <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
              <button class="btn small ghost" type="submit">Delete</button>
            </form>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2><?= $edit ? 'Edit campaign' : 'Create campaign' ?></h2>
  <form method="post" action="<?= e(url('admin/spin.php?tab=campaigns')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="campaigns">
    <input type="hidden" name="action" value="campaign_save">
    <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label>Slug<input name="slug" value="<?= e((string) ($edit['slug'] ?? '')) ?>" maxlength="100" required></label>
    <label>Name<input name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" maxlength="190" required></label>
    <label>Description<textarea name="description" rows="3" maxlength="5000"><?= e((string) ($edit['description'] ?? '')) ?></textarea></label>
    <label>Starts at<input name="starts_at" value="<?= e((string) ($edit['starts_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS" required></label>
    <label>Ends at<input name="ends_at" value="<?= e((string) ($edit['ends_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS" required></label>
    <label>Minimum order volume (₦)<input name="min_order" inputmode="decimal" value="<?= e((string) ((($edit['min_order_minor'] ?? 0)) / 100)) ?>"></label>
    <label>Spin period
      <select name="period_rule">
        <?php foreach ($rules as $k => $label) : ?>
          <option value="<?= e($k) ?>"<?= ($edit['period_rule'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Max spins per user (blank = unlimited)<input type="number" name="max_spins_per_user" value="<?= e((string) ($edit['max_spins_per_user'] ?? '')) ?>" min="1"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
    <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create campaign' ?></button>
    <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/spin.php?tab=campaigns')) ?>">Cancel</a><?php endif; ?></p>
  </form>
</div>

<?php elseif ($tab === 'prizes') : ?>
<?php if (!$camp) : ?>
  <div class="card"><p>Pick a campaign first.</p></div>
  <div class="table-scroll"><table class="data">
    <tbody><?php foreach ($campaigns as $r) : ?>
      <tr><td><a href="<?= e(url('admin/spin.php?tab=prizes&campaign=' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
<?php else : ?>
  <h2>Prizes — <?= e($camp['name']) ?></h2>
  <div class="card">
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Label</th><th>Type</th><th>Value</th><th>Weight</th><th>Wins</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($prizes as $r) : ?>
          <tr><td><strong><?= e($r['label']) ?></strong></td><td><?= e($types[$r['reward_type']]) ?></td>
            <td><?= $r['reward_type'] === 'discount' ? ((int) $r['reward_value_minor'] . '%') : ($r['reward_type'] === 'promo_code' ? e((string) $r['promo_code']) : e(format_money((int) $r['reward_value_minor']))) ?></td>
            <td><?= (int) $r['probability_weight'] ?></td>
            <td><?= (int) $r['wins_count'] ?><?= $r['max_wins'] !== null ? ' / ' . (int) $r['max_wins'] : '' ?></td>
            <td><?= (int) $r['is_active'] ? 'Yes' : 'No' ?></td>
            <td><a href="<?= e(url('admin/spin.php?tab=prizes&campaign=' . $camp['id'] . '&edit=' . (int) $r['id'])) ?>">Edit</a>
              <form method="post" action="<?= e(url('admin/spin.php?tab=prizes&campaign=' . $camp['id'])) ?>" class="inline-form" onsubmit="return confirm('Delete this prize?');">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="prizes">
                <input type="hidden" name="campaign" value="<?= (int) $camp['id'] ?>">
                <input type="hidden" name="action" value="prize_delete">
                <input type="hidden" name="item_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small ghost" type="submit">Delete</button>
              </form>
            </td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <h2><?= $edit ? 'Edit prize' : 'Add prize' ?></h2>
    <form method="post" action="<?= e(url('admin/spin.php?tab=prizes&campaign=' . $camp['id'])) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="prizes">
      <input type="hidden" name="campaign" value="<?= (int) $camp['id'] ?>">
      <input type="hidden" name="action" value="prize_save">
      <input type="hidden" name="item_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <label>Label<input name="label" value="<?= e((string) ($edit['label'] ?? '')) ?>" maxlength="150" required></label>
      <label>Reward type
        <select name="reward_type">
          <?php foreach ($types as $k => $label) : ?>
            <option value="<?= e($k) ?>"<?= ($edit['reward_type'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Value in ₦ (wallet / free-delivery)<input name="reward_value" inputmode="decimal" value="<?= e((string) ((($edit['reward_value_minor'] ?? 0)) / 100)) ?>"></label>
      <label>Percent (discount prizes, 1–90)<input type="number" name="reward_percent" value="<?= ($edit['reward_type'] ?? '') === 'discount' ? (int) $edit['reward_value_minor'] : '10' ?>" min="1" max="90"></label>
      <label>Coupon code (promo prizes)<input name="promo_code" value="<?= e((string) ($edit['promo_code'] ?? '')) ?>" maxlength="40"></label>
      <label>Probability weight (0 = never drawn)<input type="number" name="probability_weight" value="<?= (int) ($edit['probability_weight'] ?? 0) ?>" min="0" max="1000000"></label>
      <label>Max wins (blank = unlimited)<input type="number" name="max_wins" value="<?= e((string) ($edit['max_wins'] ?? '')) ?>" min="1"></label>
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= !$edit || (int) $edit['is_active'] ? ' checked' : '' ?>> Active</label>
      <p><button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add prize' ?></button>
      <?php if ($edit) : ?><a class="btn ghost" href="<?= e(url('admin/spin.php?tab=prizes&campaign=' . $camp['id'])) ?>">Cancel</a><?php endif; ?></p>
    </form>
  </div>
<?php endif; ?>

<?php elseif ($tab === 'spins') : ?>
<form method="get" action="<?= e(url('admin/spin.php')) ?>" class="filter-row">
  <input type="hidden" name="tab" value="spins">
  <label>Campaign
    <select name="f_campaign">
      <option value="0">All</option>
      <?php foreach ($campaigns as $r) : ?>
        <option value="<?= (int) $r['id'] ?>"<?= $f_campaign === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select name="status">
      <option value="">All</option>
      <?php foreach (['pending' => 'Pending', 'credited' => 'Credited', 'expired' => 'Expired', 'reversed' => 'Reversed'] as $k => $label) : ?>
        <option value="<?= e($k) ?>"<?= $f_status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p><button class="btn" type="submit">Filter</button></p>
</form>
<form method="post" action="<?= e(url('admin/spin.php?tab=spins')) ?>" class="filter-row">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="spins">
  <input type="hidden" name="action" value="expire">
  <p><button class="btn" type="submit">Expire due rewards now</button></p>
</form>
<div class="card">
  <?php if (!$spins) : ?><p>No spins yet.</p>
  <?php else : ?>
  <div class="table-scroll"><table class="data">
    <thead><tr><th>When</th><th>Customer</th><th>Campaign</th><th>Prize</th><th>Reward</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($spins as $s) : ?>
        <tr><td><?= e(substr($s['created_at'], 0, 16)) ?></td><td><?= e($s['customer_name']) ?></td>
          <td><?= e($s['campaign_name']) ?></td><td><?= e($s['label']) ?></td>
          <td><?= !empty($s['reward_code']) ? e($s['reward_code']) : (!empty($s['wallet_txn_ref']) ? e($s['wallet_txn_ref']) : '—') ?></td>
          <td><?= e(ucfirst($s['reward_status'])) ?></td>
          <td>
            <?php if ($s['reward_status'] === 'credited') : ?>
              <form method="post" action="<?= e(url('admin/spin.php?tab=spins')) ?>" class="inline-form" onsubmit="return confirm('Reverse this reward?');">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="spins">
                <input type="hidden" name="action" value="reverse">
                <input type="hidden" name="item_id" value="<?= (int) $s['id'] ?>">
                <input name="reason" maxlength="255" placeholder="Reason" required size="14">
                <button class="btn small ghost" type="submit">Reverse</button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'reports') : ?>
<div class="grid dash-grid">
  <article class="card">
    <h2>Spins by status</h2>
    <?php foreach ($reports['by_status'] as $r) : ?><p><?= e(ucfirst($r['reward_status'])) ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['by_status']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Spins by campaign</h2>
    <?php foreach ($reports['by_campaign'] as $r) : ?><p><?= e($r['name']) ?>: <strong><?= (int) $r['n'] ?></strong></p><?php endforeach; ?>
    <?php if (!$reports['by_campaign']) : ?><p class="result-meta">None yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Wallet paid out</h2>
    <p><strong><?= e(format_money((int) $reports['wallet_paid'])) ?></strong></p>
  </article>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
