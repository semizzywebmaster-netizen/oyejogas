<?php
/**
 * Oyejo Gas - monthly wallet statement, derived purely from the ledger (Phase 11).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_login();
require_permission('wallet.own');
require_once BASE_PATH . '/includes/cart.php';
require_once BASE_PATH . '/includes/wallet.php';

$me = current_user();
$cid = cart_customer_id((int) $me['id']);
$wallet = wallet_ensure($cid);

$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}
$start = $month . '-01 00:00:00';
$end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));

$s = db()->prepare(
    "SELECT COALESCE(SUM(CASE WHEN `direction` = 'credit' THEN `amount_minor` ELSE -`amount_minor` END), 0)"
    . " FROM `wallet_transactions` WHERE `wallet_id` = ? AND `status` = 'completed' AND `created_at` < ?"
);
$s->execute([$wallet['id'], $start]);
$opening = (int) $s->fetchColumn();

$s = db()->prepare(
    "SELECT * FROM `wallet_transactions` WHERE `wallet_id` = ? AND `status` = 'completed'"
    . ' AND `created_at` >= ? AND `created_at` < ? ORDER BY `id`'
);
$s->execute([$wallet['id'], $start, $end]);
$rows = $s->fetchAll();

$credits = 0;
$debits = 0;
foreach ($rows as $r) {
    if ($r['direction'] === 'credit') {
        $credits += $r['amount_minor'];
    } else {
        $debits += $r['amount_minor'];
    }
}
$closing = $opening + $credits - $debits;

$page_title = 'Statement ' . $month;
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs no-print"><a href="<?= e(url('customer/wallet.php')) ?>">Wallet</a> &rsaquo; Statement</p>
<h1>Statement — <?= e($month) ?></h1>
<form method="get" action="" class="filters no-print">
  <label>Month<input type="month" name="month" value="<?= e($month) ?>" max="<?= e(date('Y-m')) ?>"></label>
  <p><button class="btn ghost" type="submit">View</button></p>
</form>
<div class="invoice">
  <p><strong>Oyejo Gas wallet statement</strong><br><?= e($me['name']) ?> (<?= e($me['email']) ?>)</p>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Credit</th><th>Debit</th></tr></thead>
      <tbody>
        <tr><th colspan="3">Opening balance</th><td colspan="2"><?= e(format_money($opening)) ?></td></tr>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
            <td><?= e($r['reference']) ?></td>
            <td><?= e(ucfirst($r['type']) . ' — ' . ($r['narration'] ?? '')) ?></td>
            <td><?= $r['direction'] === 'credit' ? e(format_money($r['amount_minor'])) : '' ?></td>
            <td><?= $r['direction'] === 'debit' ? e(format_money($r['amount_minor'])) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><th colspan="3">Totals</th><td><?= e(format_money($credits)) ?></td><td><?= e(format_money($debits)) ?></td></tr>
        <tr><th colspan="3">Closing balance</th><td colspan="2"><strong><?= e(format_money($closing)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
  <p class="no-print"><button class="btn primary" onclick="window.print()">Print</button></p>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
