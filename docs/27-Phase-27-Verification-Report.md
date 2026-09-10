# Phase 27 verification report — Mobile, PWA and push notifications

Date: 2026-09-11 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- PWA shell: `manifest.webmanifest` (name, icons incl. maskable,
  theme/background, display standalone), `service-worker.js` (versioned
  `0.27.0` cache, GET same-origin public-only caching, admin/customer/
  driver/api/install/cron bypass, push + notificationclick handlers),
  `offline.php` standalone fallback (no bootstrap dependency), icon set
  `assets/icons/` (192/512 + maskable, real PNGs), header tags
  (manifest link, theme-color, apple-touch-icon) + install prompt
  button, `assets/js/app.js` SW registration + `OyejoPush` helper.
- Web-push backend `includes/push.php`: VAPID key loading/validation
  from env (`PUSH_VAPID_PUBLIC_KEY`/`PUSH_VAPID_PRIVATE_KEY`,
  `PUSH_VAPID_SUBJECT`), ES256 JWT with programmatic PKCS#8 DER,
 RFC8188 aes128gcm encryption (`push_encrypt`), subscription store/
  remove/list (`push_save`, `push_remove`, `push_subscriptions_for`),
  Web Push sender with 404/410 pruning + retryable classification
  (`push_send_to`, `push_broadcast`), `push_notify` fan-out (inbox +
  advisory + push), `push_warn_expiring`.
- `api/push-subscribe.php`: login + CSRF + same-origin guarded
  subscribe/unsubscribe/status endpoint with endpoint-URL + p256dh/
  auth-key validation (save/remove round-trip verified).
- Customer push opt-in UI in `customer/notifications.php`
  (`#pushEnable`, status line, VAPID key from server config).
- Schema: `push_subscriptions` (+ `vapid_public_key` setting),
  65 tables; `OYEJO_VERSION` 0.26.0 → 0.27.0.
- Hardening along the way: `assets/.htaccess` MIME/CORS + immutable
  caching, root `.htaccess` manifest MIME + icon caching, SW scope
  documented in `pwa/README.md`, push settings surfaced in installer
  env template (no secrets shipped).

## Rules enforced (server-side)

- Push subscription changes require login + valid CSRF + same-origin
  fetch; endpoint must be http(s), keys must decode to the right
  lengths (65-byte p256dh, 16-byte auth).
- VAPID keys and subject come from env only; invalid/missing keys
  close sending (`push_enabled()` false) instead of failing open.
- Dead endpoints (HTTP 404/410) are pruned from the store; other
  failures are marked retryable/failed, never silently dropped.
- SW caches GET same-origin public pages/assets only; portals, APIs,
  install and cron are never cached (verified by source assertion).
- Asset URLs carry `?v=0.27.0` for cache-busting on upgrade.

## Evidence (E2E `oyejo_p27`, fresh DB, 63/63 green)

- CLI 11/11: P1 b64url round-trip, P2 VAPID validation (bad keys
  rejected), P3 sender closed without keys, P4 ES256 JWT verifies
  against the public key (openssl round-trip byte-identical), P5
  aes128gcm decrypts per RFC draft, P6 subscribe/unsubscribe, P7 dead
  endpoint marked `failed`, P8 asset versioning, P9 inbox + advisory
  fan-out, SC-10 spin no-double-play/no-cross-claim, SC-11 forged
  checkout totals ignored (server recomputes: 2× promo unit price,
  discount 0, total = subtotal + fee).
- HTTP 52/52: W1–W8 PWA/push (manifest 200 + JSON, SW version/bypass/
  push handlers, standalone offline page, real PNG icons, homepage
  tags + versioned CSS, SW + OyejoPush in app.js, opt-in UI, push API
  anon-403/CSRF/endpoint-validation/save/remove round-trip), M1–M4
  mobile (viewport + 200 on 5 portals, hamburger, responsive CSS,
  scroll-tables), SC-01 SQLi, SC-02 stored-XSS escaping (tickets both
  desks + approved review on storefront), SC-03 CSRF, SC-04 IDOR,
  SC-05 lockout, SC-06 RBAC incl. manager POST 403, SC-07 upload
  gates (type/size/polyglot), SC-08 wallet isolation, SC-09 referral
  abuse (duplicate-phone + bogus-code), SC-12 profile tamper,
  SC-13 secret hygiene, SC-14 storage/backup blocks, SC-15 SW scope,
  F1 caching/MIME, F2 key pages < 3 s (0.006/0.004/0.003 s).
- Server log scanned: no PHP warnings/notices/fatals.
- `php tests/foundation-check.php`: 454/454.

## Issues found and fixed (before approval)

1. `push_enabled()` compared null keys against `''` with `!==`
   (always true → sender open without keys) — now strict null checks.
2. Hand-rolled SEC1 ECDSA DER was malformed (version/wrapping/
   lengths) — replaced with programmatic PKCS#8 PrivateKeyInfo;
   JWT now verifies with openssl (caught by P4, proven by round-trip).
3. Test-env only: fresh container lacked `php-mbstring` + `php-curl`
   (login 500s); installed and restarted. Harness corrected for
   routing (`?slug=`), verified-purchase review gate, review
   moderation queue, wallet top-up minimum (₦100), statement showing
   completed txns only, and anon-admin 302 → login.
4. Login CSRF field is `csrf_token`, consistent with every other
   form in the app (all logins in the suite authenticate through it);
   no app change needed.

No known issues outstanding. Approved for commit under autopilot authority.
