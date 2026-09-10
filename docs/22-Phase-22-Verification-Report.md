# Phase 22 verification report — Notifications (email/SMS/WhatsApp/push)

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (NT-01…NT-15, WA-01…WA-08)

- `includes/notify.php` — 15-event catalogue with per-event channel
  config (`notify_event_*` settings, `notify_config_save`), `{{var}}`
  templates (`notify_render`), send-time WhatsApp opt-out skip,
  `notify_send` dispatcher (unknown events/customers send 0, never
  throws), `notify_deliver_row` (push=inbox `delivered`, WA
  opt-out→`failed` attempts=999 no-retry, invalid recipients fail
  fast), rate-limited worker with mid-loop enforcement,
  5min×attempts backoff, `notify_retry`, capped promo blast
  (`notify_promo`: email→newsletter, WA/SMS→customers, opt-outs
  excluded), pickup reminders with sentinel dedupe (sentinels hidden
  from the inbox).
- `includes/mailer.php` — `send_mail` (UTF-8, MAIL_REAL-gated),
  generic cURL JSON providers for SMS/WhatsApp, always logged to
  `storage/logs/{mail,sms,whatsapp}.log`; log-mode sends mark `sent`
  with `provider='log'`.
- `admin/notifications.php` (`notifications.view`, send actions need
  `notifications.send`) — queue (filters, run worker, reminders,
  retry), template CRUD, per-event channel checkboxes, provider
  status (no secrets shown), WA opt-in list, promo blast form.
- `customer/notifications.php` — inbox + WA opt-in/out toggle.
- Backfills: wallet top-up approval/adjustment → `wallet_transaction`
  (NT-11); spin wallet win → `spin_reward` (NT-13). NT-01/02/03 stay
  direct `send_mail` (pre-login, email-only) by design.
- `cron/send-notifications.php` (every minute) +
  `cron/pickup-reminders.php` (daily 08:00), CLI-only (403 over
  HTTP); `cron/README.md` documents both.
- Seeds: 9 templates, `notify_rate_per_minute=30`,
  `notify_max_attempts=5`; `.env.example` gains `SMS_API_URL` +
  `NOTIFY_*`. Credentials env-only, never displayed (WA-08).
- Drive-by fix: `config/database.php` sets the session `time_zone`
  from PHP so MySQL `NOW()/CURDATE()` match app wall time (E2E
  caught a 1h UTC/Lagos skew breaking retry-window comparisons).

## Rules enforced (server-side)

- Unknown events and unknown customers queue nothing; sends never
  throw into business flows (wallet/spin hooks wrapped).
- Per-channel feature toggles checked at send and delivery; WA
  missing-row = subscribed, explicit 0 = opted out (skipped at send,
  no-retry fail if already queued).
- Promo capped (200/channel default), queued — delivery rate-limited
  by the worker like everything else.
- All staff actions permission-gated + CSRF + `notify.*` audit trail.

## Evidence (E2E `oyejo_p22`, fresh DB, 47/47 green)

- CLI 29/29: catalogue/render/dispatch, log-provider delivery,
  config save→sms+push, invalid inputs→0, rate limit 2/min (sends
  2, leaves 1, drains after restore), provider-fail backoff
  (attempts=1, future `next_retry_at`, error kept) → failed at max,
  retry reset, WA opt-out skip/fail-999/opt-in resend, promo 2+2
  with validation, reminders 2/queue + 0/dedupe + sentinel hidden,
  wallet top-up + forced spin win backfills, template override +
  validation, audits ≥5, all channel logs written.
- HTTP 18/18: owner login, all 5 desk tabs, config save/restore,
  template create/delete, desk promo (2 queued), desk retry + cron
  worker delivery, customer inbox + WA off/on persisted, customer
  desk 403, CSRF rejections, cron 403 over HTTP, cron CLIs run,
  wrong password rejected.
- `php tests/foundation-check.php`: 365/365 (4 new file checks + 11
  new content checks).

## Issues found and fixed (before approval)

1. `notify_send` queued a row for unknown events — now returns 0
   (legacy `delivery_update` still flows via `notify_emit`).
2. DB session ran UTC while PHP ran Africa/Lagos — `db()` now sets
   the session offset from the app timezone (verified: NOW() ==
   PHP time to the second).
3. Test harness bugs (not app bugs): template `{{name}}` needs the
   send-merged vars, `[ok,msg]` mis-destructured as truthy arrays,
   pickup/spin fixture columns, inverted PASS/FAIL helper, stale
   rerun state — all fixed; final run is clean-room green.

No known issues outstanding. Approved for commit under autopilot authority.
