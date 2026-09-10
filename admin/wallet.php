<?php
/**
 * Oyejo Gas - staff wallet desk: top-up approvals, adjustments, reversals,
 * freeze control (Phase 11). Broader admin console arrives in Phase 15.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('portal.admin');
require_once BASE_PATH . '/includes/wallet.php';

$me = current_user();
$uid = (int) $me['id'];
$message = '';
$errors = [];
$focus = null; // customer row under review

function staff_find_customer($q) {
    $q = trim((string) $q);
    if ($q === '') {
        return null;
    }
    $s = db()->prepare(
        'SELECT `c`.*, `u`.`name`, `u`.`email` FROM `customers` `c`'
        . ' JOIN `users` `u` ON `u`.`id` = `c`.`user_id`'
        . ' WHERE `u`.`email` = ? OR `c`.`customer_code` = ? LIMIT 1'
    );
    $s->execute([$q, $q]);
    return $s->fetch() ?: null;
}

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } else {
        $action = (string) post('action', '');
        if ($action === 'find') {
            $focus = staff_find_customer(post('q', ''));
            if (!$focus) {
                $errors[] = 'No customer with that email or code.';
            }
        } elseif ($action === 'decide') {
            require_permission('payments.verify');
            list($ok, $msg) = wallet_decide_topup(post('txn_id', 0), $uid, (string) post('decision', '') === 'approve', post('note', ''));
            $ok ? $message = $msg : $errors[] = $msg;
            $focus = staff_find_customer(post('q', ''));
        } elseif ($action === 'adjust') {
            require_permission('wallet.adjust');
            $focus = staff_find_customer(post('q', ''));
            if (!$focus) {
                $errors[] = 'Find the customer first.';
            } else {
                $minor = wallet_parse_amount(post('amount', ''));
                if ($minor === null) {
                    $errors[] = 'Enter a valid amount.';
                } else {
                    list($ok, $msg) = wallet_adjust($focus['id'], $uid, post('direction', 'credit'), $minor, post('reason', ''));
                    $ok ? $message = $msg : $errors[] = $msg;
                }
            }
        } elseif ($action === 'reverse') {
            require_permission('wallet.adjust');
            list($ok, $msg) = wallet_reverse(post('txn_id', 0), $uid, post('reason', ''));
            $ok ? $message = $msg : $errors[] = $msg;
            $focus = staff_find_customer(post('q', ''));
        } elseif ($action === 'freeze') {
            require_permission('wallet.adjust');
            $focus = staff_find_customer(post('q', ''));
            if (!$focus) {
                $errors[] = 'Find the customer first.';
            } else {
                list($ok, $msg) = wallet_freeze($focus['id'], $uid, (string) post('frozen', '') === '1', post('reason', ''));
                $ok ? $message = $msg : $errors[] = $msg;
            }
        }
    }
}
if (!$focus && isset($_GET['q'])) {
    $focus = staff_find_customer($_GET['q']);
}

$pending = db()->query(
    "SELECT `t`.*, `u`.`email` FROM `wallet_transactions` `t`"
    . ' JOIN `wallets` `w` ON `w`.`id` = `t`.`wallet_id`'
    . ' JOIN `customers` `c` ON `c`.`id` = `w`.`customer_id`'
    . ' JOIN `users` `u` ON `u`.`id` = `c`.`user_id`'
    . " WHERE `t`.`type` = 'topup' AND `t`.`status` = 'pending' ORDER BY `t`.`id` LIMIT 50"
)->fetchAll();

$focus_wallet = null;
$focus_txns = [];
if ($focus) {
    $focus_wallet = wallet_ensure($focus['id']);
    $focus_txns = wallet_history($focus_wallet['id'], '', '', 20);
}

$audits = db()->query(
    "SELECT `a`.*, `u`.`email` AS `actor` FROM `audit_logs` `a`"
    . ' LEFT JOIN `users` `u` ON `u`.`id` = `a`.`user_id`'
    . " WHERE `a`.`entity_type` = 'wallet' ORDER BY `a`.`id` DESC LIMIT 20"
)->fetchAll();

$page_title = 'Wallet desk';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Wallet desk</p>
<h1>Wallet desk</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>

<div class="card">
  <h2>Pending top-ups (<?= count($pending) ?>)</h2>
  <?php if (!$pending) : ?><p class="result-meta">Nothing awaiting review.</p>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Reference</th><th>Customer</th><th>Amount</th><th>Note</th><th>When</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pending as $p) : ?>
            <tr>
              <td><?= e($p['reference']) ?></td><td><?= e($p['email']) ?></td>
              <td><?= e(format_money($p['amount_minor'])) ?></td><td><?= e((string) $p['narration']) ?></td>
              <td><?= e(substr($p['created_at'], 0, 16)) ?></td>
              <td>
                <form method="post" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="decide">
                  <input type="hidden" name="txn_id" value="<?= (int) $p['id'] ?>">
                  <?php if (has_permission('payments.verify')) : ?>
                    <button class="btn primary" type="submit" name="decision" value="approve">Approve</button>
                    <button class="btn ghost" type="submit" name="decision" value="reject">Reject</button>
                  <?php else : ?>
                    <span class="result-meta">No approval permission</span>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Find customer</h2>
  <form method="post" action="" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="find">
    <label>Email or customer code<input name="q" value="<?= e($focus['email'] ?? '') ?>" maxlength="190"></label>
    <p><button class="btn primary" type="submit">Find</button></p>
  </form>
</div>

<?php if ($focus && $focus_wallet) : ?>
  <div class="card">
    <h2><?= e($focus['name']) ?> (<?= e($focus['email']) ?>)</h2>
    <p>Balance: <strong><?= e(format_money($focus_wallet['balance_minor'])) ?></strong>
      · Status: <?= e($focus_wallet['status']) ?> · Code: <?= e($focus['customer_code']) ?></p>
    <?php if (has_permission('wallet.adjust')) : ?>
      <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="q" value="<?= e($focus['email']) ?>">
        <label>Direction
          <select name="direction"><option value="credit">Credit</option><option value="debit">Debit</option></select>
        </label>
        <label>Amount (₦)<input name="amount" inputmode="decimal" required></label>
        <label>Reason (required)<input name="reason" required maxlength="255"></label>
        <p><button class="btn primary" type="submit">Post adjustment</button></p>
      </form>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="freeze">
        <input type="hidden" name="q" value="<?= e($focus['email']) ?>">
        <input type="hidden" name="frozen" value="<?= $focus_wallet['status'] === 'active' ? '1' : '0' ?>">
        <p><button class="btn ghost" type="submit"><?= $focus_wallet['status'] === 'active' ? 'Freeze wallet' : 'Unfreeze wallet' ?></button></p>
      </form>
    <?php endif; ?>
    <h3>Recent transactions</h3>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Reference</th><th>Type</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($focus_txns as $t) : ?>
            <tr>
              <td><?= e($t['reference']) ?></td><td><?= e($t['type']) ?></td>
              <td><?= $t['direction'] === 'credit' ? '+' : '−' ?><?= e(format_money($t['amount_minor'])) ?></td>
              <td><?= e($t['status']) ?></td>
              <td>
                <?php if ($t['status'] === 'completed' && has_permission('wallet.adjust')) : ?>
                  <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reverse">
                    <input type="hidden" name="txn_id" value="<?= (int) $t['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($focus['email']) ?>">
                    <input type="hidden" name="reason" value="staff reversal from wallet desk">
                    <button class="btn ghost" type="submit" onclick="return confirm('Reverse <?= e($t['reference']) ?>?')">Reverse</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Wallet audit trail</h2>
  <?php if (!$audits) : ?><p class="result-meta">No wallet audit rows yet.</p>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($audits as $a) : ?>
            <tr><td><?= e(substr($a['created_at'], 0, 16)) ?></td><td><?= e($a['actor'] ?: 'system') ?></td>
              <td><?= e($a['action']) ?></td><td><?= e((string) ($a['new_values'] ?? '')) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
