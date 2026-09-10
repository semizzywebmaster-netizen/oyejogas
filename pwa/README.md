# PWA (Phase 27)

Installable, offline-tolerant storefront:

- `manifest.webmanifest` — name, icons (any + maskable, 192/512),
  standalone display, theme color.
- `service-worker.js` — precached shell (home, offline page, CSS, JS,
  icons); cache-first for `/assets/*`, network-first with offline
  fallback for public navigations; push display + click-through.
  Security: only same-origin GETs cached; `/admin`, `/customer`,
  `/driver`, `/api`, `/install`, `/cron`, cart and checkout always
  bypass the cache. Keep `VERSION` in sync with `OYEJO_VERSION`.
- `offline.php` — standalone fallback (no bootstrap, no database).
- Push: subscriptions via `api/push-subscribe.php` into
  `push_subscriptions`; delivery by `includes/push.php` (VAPID +
  aes128gcm, keys in `VAPID_*` env); opt-in on the customer
  notifications page; event queue fans out best-effort on the `push`
  channel. Failures log to `storage/logs/push.log` (visible under
  Admin → Error logs); 404/410 endpoints are dropped automatically.
- Performance: every `asset()` URL carries `?v=<platform version>`;
  `/assets/.htaccess` caches far-future immutable.
