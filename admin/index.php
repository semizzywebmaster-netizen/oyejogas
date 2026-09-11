<?php
/**
 * Oyejo Gas - admin dashboard (Phase 15).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
require_once BASE_PATH . '/includes/admin.php';

$d = adm_dashboard();
require_once BASE_PATH . '/includes/sidebar.php';
$desks = sidebar_admin_desks();
$page_title = 'Admin console';
require BASE_PATH . '/includes/header.php';
?>
<p class="crumbs">Admin</p>
<h1>Admin console</h1>
<div class="stat-grid">
  <div class="card stat"><span class="stat-num"><?= number_format($d['customers']) ?></span><span class="stat-label">Customers</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['staff']) ?></span><span class="stat-label">Staff</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['orders_open']) ?></span><span class="stat-label">Open orders</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['orders_today']) ?></span><span class="stat-label">Orders today</span></div>
  <div class="card stat"><span class="stat-num">₦<?= number_format($d['revenue_minor'] / 100, 2) ?></span><span class="stat-label">Paid revenue</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['refills_open']) ?></span><span class="stat-label">Open refills</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['pickups_open']) ?></span><span class="stat-label">Open pickups</span></div>
  <div class="card stat"><span class="stat-num"><?= number_format($d['low_stock']) ?></span><span class="stat-label">Low-stock items</span></div>
</div>
<div class="card">
  <h2>Desks</h2>
  <p class="filter-row">
    <?php foreach ($desks as [$label, $path, $perm]) : ?>
      <?php if (has_permission($perm)) : ?>
        <a class="btn small ghost" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </p>
</div>
<?php $addon_desks = addon_menus(); ?>
<?php if ($addon_desks) : ?>
<div class="card">
  <h2>Add-on desks</h2>
  <p class="filter-row">
    <?php foreach ($addon_desks as [$label, $path, $perm]) : ?>
      <?php if (has_permission($perm)) : ?>
        <a class="btn small ghost" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </p>
</div>
<?php endif; ?>
<div class="card">
  <h2>Recent orders</h2>
  <?php if (!$d['recent_orders']) : ?><p class="result-meta">No orders yet.</p>
  <?php else : ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th></tr></thead>
      <tbody>
        <?php foreach ($d['recent_orders'] as $o) : ?>
          <tr><td><a href="<?= e(url('admin/orders.php?view=' . (int) $o['id'])) ?>"><?= e($o['order_number']) ?></a></td>
            <td><?= e($o['customer_name']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $o['status']))) ?></td>
            <td>₦<?= number_format((int) $o['total_minor'] / 100, 2) ?></td><td><?= e($o['created_at']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
