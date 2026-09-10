<?php
/**
 * Oyejo Gas - about page (Phase 7).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
$page_title = 'About us';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">About Oyejo Gas</p>
  <h1>Safe gas, honest weight, fast delivery.</h1>
</section>
<section class="split">
  <div>
    <h2>Who we are</h2>
    <p>Oyejo Gas is a Lagos-based LPG company combining a modern online shop
      with dependable doorstep logistics. We sell new cylinders, refill yours,
      exchange empties for full ones, and deliver everything on a schedule
      that suits you.</p>
    <h2>Safety first</h2>
    <p>Every cylinder we deliver is inspected, correctly filled, sealed and
      weighed. Damaged or expired cylinders are retired — never refilled.</p>
  </div>
  <div>
    <div class="card">
      <h2>What we do</h2>
      <ul class="ticks">
        <li>LPG cylinder sales in all sizes</li>
        <li>Gas refills with pickup &amp; delivery</li>
        <li>Empty-for-full cylinder exchange</li>
        <li>Regulators, hoses &amp; accessories</li>
      </ul>
    </div>
    <div class="card">
      <h2>Talk to us</h2>
      <p>Questions about sizes, deposits or delivery areas?</p>
      <p><a class="btn primary" href="<?= e(url('contact.php')) ?>">Contact us</a></p>
    </div>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
