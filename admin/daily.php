<?php
/**
 * Oyejo Gas - daily rewards desk: today's check-ins and totals.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_permission('daily.manage');
require_once BASE_PATH . '/includes/admin.php';
require_once BASE_PATH . '/includes/daily.php';

$stats = ['today' => 0, 'week' => 0, 'paid_today' => 0, 'longest' => 0];
$rows = [];
try {
    $stats = daily_admin_stats();
    $rows = daily_admin_today();
} catch (Throwable $t) {
    report_error('system', 'error', $t);
}

$page_title = 'Daily rewards';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('admin/')) ?>">Admin</a> &rsaquo; Daily rewards</p>
<h1>Daily rewards</h1>
<p class="result-meta">Check-in credits are paid server-side into the customer wallet. Amounts are configured under Settings → Daily rewards.</p>

<div class="stat-grid">
  <div class="card stat"><span class="stat-num"><?= number_format($stats['today']) ?></span><span class="stat-label">Check-ins today</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($stats['week']) ?></span><span class="stat-label">Last 7 days</span></div>
  <div class="card stat"><span class="stat-num"><?= e(format_money($stats['paid_today'])) ?></span><span class="stat-label">Paid today</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($stats['longest']) ?></span><span class="stat-label">Longest streak</span></div>
</div>

<p><a class="btn small ghost" href="<?= e(url('admin/settings.php')) ?>">Edit daily amounts</a></p>

<div class="card">
  <h2>Today’s check-ins</h2>
  <?php if (!$rows) : ?>
    <p class="result-meta">Nobody has checked in yet today.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>When</th><th>Customer</th><th>Streak</th><th>Credit</th><th>Mystery</th><th>Ref</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td><?= e(substr($r['created_at'], 11, 8)) ?></td>
            <td><?= e($r['name']) ?> <span class="result-meta">(<?= e($r['customer_code']) ?>)</span></td>
            <td><?= (int) $r['streak'] ?></td>
            <td><?= e(format_money((int) $r['reward_minor'])) ?></td>
            <td><?= (int) $r['bonus_minor'] > 0 ? e(format_money((int) $r['bonus_minor'])) : '—' ?></td>
            <td><code><?= e((string) $r['wallet_txn_ref']) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
