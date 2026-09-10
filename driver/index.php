<?php
/**
 * Oyejo Gas - driver portal stub (full logistics in Phase 16).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
$page_title = 'Driver portal';
require BASE_PATH . '/includes/header.php';
?>
<section class="stub">
  <p class="pill">Driver portal</p>
  <h1>Driver portal</h1>
  <p class="lede">Driver accounts, dispatch and deliveries arrive in <strong>Phase 16</strong>.</p>
  <div class="card">
    <h2>On the roadmap</h2>
    <ul class="ticks">
      <li>Delivery &amp; pickup assignments</li>
      <li>Status updates &amp; proof of delivery</li>
      <li>Cash collection &amp; performance</li>
    </ul>
  </div>
  <p><a class="btn ghost" href="<?= e(url('')) ?>">&larr; Back home</a></p>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
