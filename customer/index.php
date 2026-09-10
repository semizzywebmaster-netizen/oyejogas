<?php
/**
 * Oyejo Gas - customer portal home (full dashboard in Phase 10).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
$page_title = 'My account';
require BASE_PATH . '/includes/header.php';
?>
<?php if (!is_logged_in()) : ?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>My account</h1>
  <p class="lede">Log in to shop, track orders and manage your wallet.</p>
  <p class="cta">
    <a class="btn primary" href="<?= e(url('customer/login.php')) ?>">Log in</a>
    <a class="btn ghost" href="<?= e(url('customer/register.php')) ?>">Create account</a>
  </p>
</section>
<?php else : require_permission('portal.customer'); $u = current_user(); ?>
<section class="stub">
  <p class="pill">Customer portal</p>
  <h1>Welcome, <?= e($u['name']) ?>!</h1>
  <p class="lede">Signed in as <?= e($u['email']) ?>.</p>
  <div class="card">
    <h2>Coming in Phase 10</h2>
    <ul class="ticks">
      <li>Order history, tracking &amp; invoices</li>
      <li>Wallet, referrals &amp; rewards</li>
      <li>Addresses, notifications &amp; support</li>
    </ul>
  </div>
  <p><a href="<?= e(url('customer/verify-phone.php')) ?>">Verify phone number</a> &middot; <a href="<?= e(url('customer/logout.php')) ?>">Log out</a></p>
</section>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
