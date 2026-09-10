<?php
// Runs inside admin/addon.php: bootstrap, $slug, e(), url(), setting() available.
if (!defined('OYEJO_BOOT')) { http_response_code(403); exit('Forbidden'); }
?>
<div class="card">
  <p><?= e((string) (setting('addon_example_welcome') ?: 'Welcome!')) ?></p>
  <p class="result-meta">Widget toggle: <?= oyejo_feature('addon_example_widget') ? 'on' : 'off' ?>.</p>
</div>
