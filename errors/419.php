<?php
// Standalone error page (works even when the app is broken).
http_response_code(419);
$ref = isset($ref) && is_string($ref) && $ref !== '' ? $ref : 'ERR-' . date('Ymd-His');
$msg = isset($msg) && is_string($msg) && $msg !== '' ? $msg : 'This link or session has expired.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Link expired — Oyejo Gas</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f8f6;color:#1c2430;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
main{background:#fff;border:1px solid #e3e8e3;border-radius:14px;padding:40px;max-width:480px;text-align:center}
.code{font-size:56px;font-weight:800;color:#0b6b3a;margin:0}
a{color:#0b6b3a}
code{background:#f0f3f0;padding:2px 6px;border-radius:6px}
</style>
</head>
<body>
<main>
  <p class="code">419</p>
  <h1><?= htmlspecialchars($msg) ?></h1>
  <p>Please request a fresh link or reload the page and try again.<br>Reference: <code><?= htmlspecialchars($ref) ?></code></p>
  <p><a href="/">Back to homepage</a> &middot; <a href="#" onclick="history.back();return false;">Go back</a></p>
</main>
</body>
</html>
