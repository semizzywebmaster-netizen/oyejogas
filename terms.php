<?php
/**
 * Oyejo Gas - terms of service (Phase 7; editable CMS version in Phase 19).
 */
require_once __DIR__ . '/includes/bootstrap.php';
reject_path_info();
$page_title = 'Terms of service';
require BASE_PATH . '/includes/header.php';
?>
<section class="page-hero">
  <p class="pill">The fine print</p>
  <h1>Terms of service</h1>
</section>
<section class="stub legal" style="max-width:760px">
  <div class="card">
    <h2>1. Orders &amp; payment</h2>
    <p>Orders are confirmed after payment verification (or rider confirmation
      for cash on delivery). Prices and delivery fees shown at checkout apply.
      Promotional prices apply only within their stated windows.</p>
    <h2>2. Cylinders &amp; deposits</h2>
    <p>Company-owned cylinders remain our property; deposits are refundable
      when the cylinder is returned in acceptable condition. Customer-owned
      cylinders are tracked by serial number wherever possible.</p>
    <h2>3. Refills, pickup &amp; delivery</h2>
    <p>Refill and pickup time windows are estimates; we notify you of delays.
      Please ensure an adult is present to receive gas products. Failed
      deliveries caused by unreachable customers may attract a re-delivery fee.</p>
    <h2>4. Safety</h2>
    <p>Never refill, repair or modify cylinders yourself. Report damaged
      cylinders to us immediately for safe collection.</p>
    <h2>5. Cancellations &amp; refunds</h2>
    <p>Orders can be cancelled free of charge before dispatch. Refunds go to
      your wallet by default, or to the original payment method on request.</p>
    <h2>6. Accounts</h2>
    <p>You are responsible for activity under your account. We may suspend
      accounts involved in fraud, abuse or safety violations.</p>
    <h2>7. Changes</h2>
    <p>We may update these terms; material changes will be announced on the site.</p>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
