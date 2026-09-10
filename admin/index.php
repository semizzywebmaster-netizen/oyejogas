<?php
/**
 * Oyejo Gas - admin console stub (full administration in Phase 15).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
require_permission('portal.admin');
$page_title = 'Admin console';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Admin portal</p>
  <h1>Admin console</h1>
  <p class="lede">Full administration unlocks in <strong>Phase 15</strong> (this area is protected by role-based access).</p>
  <div class="card">
    <h2>On the roadmap</h2>
    <ul class="ticks">
      <li>Orders, refills, pickups &amp; deliveries</li>
      <li>Customers, staff, drivers &amp; roles</li>
      <li>Payments, wallets, coupons &amp; settings</li>
    </ul>
  </div>
  <p><a class="btn ghost" href="<?= e(url('')) ?>">&larr; Back home</a></p>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
