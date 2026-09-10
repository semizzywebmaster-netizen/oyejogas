<?php
// Standalone maintenance page (works even when the app is broken). Full handler: Phase 25.
http_response_code(503);
if (PHP_SAPI !== 'cli') {
    header('Retry-After: 3600');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scheduled maintenance — Oyejo Gas</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f8f6;color:#1c2430;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
main{background:#fff;border:1px solid #e3e8e3;border-radius:14px;padding:40px;max-width:480px;text-align:center}
.code{font-size:56px;font-weight:800;color:#0b6b3a;margin:0}
a{color:#0b6b3a}
</style>
</head>
<body>
<main>
  <p class="code">503</p>
  <h1>Scheduled maintenance is in progress.</h1>
  <p>We&rsquo;re tuning up the shop. Please check back soon &mdash; your account, wallet and orders are safe.</p>
  <p><a href="/">Try again</a></p>
</main>
</body>
</html>
