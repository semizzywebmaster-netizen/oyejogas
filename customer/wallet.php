<?php
/**
 * Oyejo Gas - customer wallet: balance, top-up requests, history (Phase 11).
 * Statements live in statement.php. Figures always come from the ledger.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('wallet.own');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/wallet.php';

$me = current_user();
$uid = (int) $me['id'];
$cid = cart_customer_id($uid);
$wallet = wallet_ensure($cid);
$message = '';
$errors = [];

if (request_method() === 'POST') {
    if (!csrf_verify(post('csrf_token'))) {
        $errors[] = 'Security token mismatch. Reload and try again.';
    } elseif ((string) post('action', '') === 'topup') {
        $minor = wallet_parse_amount(post('amount', ''));
        if ($minor === null) {
            $errors[] = 'Enter a valid amount (e.g. 5000 or 5000.50).';
        } else {
            list($row, $errs) = wallet_request_topup($cid, $uid, $minor, post('method', 'transfer'), post('note', ''));
            if ($row) {
                $message = 'Top-up ' . $row['reference'] . ' recorded as pending. We will confirm your transfer shortly.';
            } else {
                $errors = array_merge($errors, $errs);
            }
        }
    }
}

$wallet = wallet_ensure($cid);
list($stored, $derived, $match) = wallet_check($wallet['id']);
$lim = wallet_limits();
$ftype = (string) ($_GET['type'] ?? '');
$fstatus = (string) ($_GET['status'] ?? '');
$txns = wallet_history($wallet['id'], $ftype, $fstatus);
$types = ['topup' => 'Top-up', 'payment' => 'Payment', 'refund' => 'Refund', 'promo' => 'Promo', 'referral' => 'Referral', 'spin' => 'Spin', 'adjustment' => 'Adjustment', 'reversal' => 'Reversal'];

$page_title = 'Wallet';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('customer/')) ?>">My account</a> &rsaquo; Wallet</p>
<h1>Wallet</h1>
<?php if ($message !== '') : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php foreach ($errors as $e) : ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<?php if ($wallet['status'] !== 'active') : ?>
  <div class="alert alert-error">Your wallet is frozen. Contact support.</div>
<?php endif; ?>
<div class="grid">
  <article class="card">
    <h3><?= e(format_money($stored)) ?></h3>
    <p>Available balance <?= $match ? '<span class="stock ok">Ledger verified</span>' : '<span class="stock out">Mismatch — contact support</span>' ?></p>
    <p><a href="<?= e(url('customer/statement.php')) ?>">Monthly statement</a></p>
  </article>
  <article class="card">
    <h2>Top up</h2>
    <p class="result-meta">Min <?= e(format_money($lim['wallet_topup_min_minor'])) ?> · max <?= e(format_money($lim['wallet_topup_max_minor'])) ?> per request ·
      <?= (int) $lim['wallet_daily_topup_count'] ?> requests/day · cap <?= e(format_money($lim['wallet_balance_cap_minor'])) ?>.</p>
    <form method="post" action="" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="topup">
      <label>Amount (₦)<input name="amount" inputmode="decimal" required placeholder="5000"></label>
      <label>Method
        <select name="method">
          <option value="transfer">Bank transfer</option>
          <option value="online">Online (gateway opens in Phase 17)</option>
        </select>
      </label>
      <label>Sender name / note (optional)<input name="note" maxlength="150"></label>
      <p><button class="btn primary" type="submit">Request top-up</button></p>
    </form>
  </article>
</div>
<div class="card">
  <h2>Transaction history</h2>
  <form method="get" action="" class="filters">
    <label>Type
      <select name="type">
        <option value="">All</option>
        <?php foreach ($types as $k => $v) : ?><option value="<?= $k ?>"<?= $ftype === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="">All</option>
        <?php foreach (['pending', 'completed', 'failed', 'reversed'] as $st) : ?><option value="<?= $st ?>"<?= $fstatus === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <p><button class="btn ghost" type="submit">Filter</button></p>
  </form>
  <?php if (!$txns) : ?><p class="result-meta">No transactions yet.</p>
  <?php else : ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Amount</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
          <?php foreach ($txns as $t) : ?>
            <tr>
              <td><?= e(substr($t['created_at'], 0, 16)) ?></td>
              <td><?= e($t['reference']) ?></td>
              <td><?= e($types[$t['type']] ?? $t['type']) ?></td>
              <td class="<?= $t['direction'] === 'credit' ? 'txn-credit' : 'txn-debit' ?>"><?= $t['direction'] === 'credit' ? '+' : '−' ?><?= e(format_money($t['amount_minor'])) ?></td>
              <td><?= e(ucfirst($t['status'])) ?></td>
              <td><?= e((string) ($t['narration'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
