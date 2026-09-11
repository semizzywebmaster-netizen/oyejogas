<?php
/**
 * Oyejo Gas - shared page footer (Phase 7 links).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<?php if (!empty($GLOBALS['oyejo_sidebar'])) : ?></div></div><?php endif; ?>
</main>
<footer class="site">
  <div class="wrap foot">
    <div>
      <strong><?= e(setting('site_name', APP_NAME)) ?></strong>
      <p><?= e(setting('tagline', 'LPG e-commerce, gas refills, cylinder exchange, pickup &amp; delivery across Lagos.')) ?></p>
    </div>
    <div>
      <strong>Company</strong>
      <p><a href="<?= e(url('about.php')) ?>">About</a><br>
      <a href="<?= e(url('contact.php')) ?>">Contact</a><br>
      <a href="<?= e(url('faq.php')) ?>">FAQ</a><br>
      <a href="<?= e(url('blog.php')) ?>">Blog</a><br>
      <a href="<?= e(url('newsletter.php')) ?>">Newsletter</a></p>
    </div>
    <div>
      <strong>Legal</strong>
      <p><a href="<?= e(url('terms.php')) ?>">Terms of service</a><br>
      <a href="<?= e(url('privacy.php')) ?>">Privacy policy</a></p>
    </div>
    <div>
      <strong>Portals</strong>
      <p><a href="<?= e(url('customer/')) ?>">Customer</a> &middot; <a href="<?= e(url('driver/')) ?>">Driver</a></p>
      <p>Platform v<?= e(OYEJO_VERSION) ?></p>
    </div>
  </div>
  <div class="wrap tiny">&copy; <?= e(date('Y')) ?> <?= e(setting('site_name', APP_NAME)) ?>. All rights reserved.
    <span id="installWrap" hidden> &middot; <button type="button" id="installApp" class="linklike">Install app</button></span></div>
</footer>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
