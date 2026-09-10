<?php
// Oyejo Gas - offline fallback page (Phase 27, PW-05).
// Standalone on purpose: no bootstrap, no database, cacheable by the
// service worker, safe to show when the network or server is down.
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b6b3a">
<title>You are offline — Oyejo Gas</title>
<style>
  body { margin: 0; font-family: system-ui, sans-serif; background: #f6f8f6; color: #1c2430; }
  .wrap { max-width: 560px; margin: 12vh auto 0; padding: 0 20px; text-align: center; }
  .card { background: #fff; border: 1px solid #e3e8e3; border-radius: 14px; padding: 36px 28px; }
  h1 { color: #0b6b3a; font-size: 24px; margin: 0 0 12px; }
  p { color: #5b6472; }
  a.btn { display: inline-block; margin-top: 12px; background: #0b6b3a; color: #fff;
    text-decoration: none; padding: 10px 26px; border-radius: 10px; font-weight: 700; }
</style>
</head>
<body>
<div class="wrap"><div class="card">
  <h1>You are offline</h1>
  <p>Oyejo Gas could not be reached. Check your connection and try again —
  your cart and account are safe.</p>
  <p><a class="btn" href="./index.php">Try again</a></p>
</div></div>
</body>
</html>
