<?php
/**
 * Oyejo Gas - customer portal stub (accounts in Phase 5, dashboard in Phase 10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
$page_title = 'My account';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>My account</h1>
  <p class="lede">Accounts arrive in <strong>Phase 5</strong>; the full dashboard in <strong>Phase 10</strong>.</p>
  <div class="card">
    <h2>On the roadmap</h2>
    <ul class="ticks">
      <li>Order history, tracking &amp; invoices</li>
      <li>Wallet, referrals &amp; rewards</li>
      <li>Addresses, notifications &amp; support</li>
    </ul>
  </div>
  <p><a class="btn ghost" href="<?= e(url('')) ?>">&larr; Back home</a></p>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
