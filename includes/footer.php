<?php
/**
 * Oyejo Gas - shared page footer.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
</main>
<footer class="site">
  <div class="wrap foot">
    <div>
      <strong><?= e(APP_NAME) ?></strong>
      <p>LPG e-commerce, gas refills, cylinder exchange, pickup &amp; delivery.</p>
    </div>
    <div>
      <strong>Portals</strong>
      <p><a href="<?= e(url('customer/')) ?>">Customer</a> &middot; <a href="<?= e(url('driver/')) ?>">Driver</a> &middot; <a href="<?= e(url('admin/')) ?>">Admin</a></p>
    </div>
    <div>
      <strong>Platform</strong>
      <p>Foundation v<?= e(OYEJO_VERSION) ?> &middot; Single-folder build</p>
    </div>
  </div>
  <div class="wrap tiny">&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?>. All rights reserved.</div>
</footer>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
