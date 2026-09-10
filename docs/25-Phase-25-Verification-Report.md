# Phase 25 verification report — Error handling, logging, recovery

Date: 2026-09-11 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/errors.php` — 8 failure domains (auth, payments,
  orders, wallet, notifications, database, files, system), 5
  levels, `ERR-…` reference numbers, JSON `app.log` writer
  (`log_event`), `report_error` (file log + server log + deduped
  `error_reports` row by signature; first critical occurrence
  alerts the admin), `show_error` (safe pages for
  403/404/419/429/500/503; plain-text + exit 1 on CLI).
- Bootstrap: exception handler reports (PDOException →
  `database`, else `system`) and shows safe 500/CLI text in
  production (debug dump kept in debug); shutdown capture logs
  fatals (E_ERROR/E_PARSE/…) with refs.
- New `errors/419.php` + `errors/429.php`; `errors/500.php`
  honors a passed `$ref`.
- 419: expired/invalid/missing reset links on GET
  (`customer/reset-password.php`); POST keeps inline form errors.
- 429: DB-backed fixed-window `rate_limit()` (hashed keys, no raw
  IPs/emails, fails open, opportunistic purge) on contact (5/h),
  newsletter (10/h), password-reset (5/h) and registration
  (10/h); 429 page carries `Retry-After`.
- Catch migrations (safe messages unchanged, one per domain):
  auth register, payments notify/verify, receipt upload (files),
  order placement, wallet top-up decision, notify worker, backup
  failures (system — also tracked, not just alerted).
- `error_reports` + `rate_limits` tables (63 tables); `logs.view`
  / `logs.manage` permissions (super_admin/admin inherit);
  `log_max_mb`/`log_keep_files` settings.
- `admin/logs.php` desk (`logs.view`; resolve/rotate need
  `logs.manage`): error reports (open/resolved/all, occurrences,
  resolver, audited resolve), whitelisted log files with sizes,
  200-line tail viewer, rotate-now. Console link added.
- `logs_rotate()` + `cron/rotate-logs.php` (CLI-only, daily):
  size-based rotation keeping 5 files per base log.
- Maintenance (Phase 23) retained as the recovery posture.

## Rules enforced (server-side)

- Users never see internals: safe messages + ref numbers only;
  details stay in `storage/logs/` (HTTP-denied) and the DB.
- Logs desk is permission-gated; only whitelisted files readable;
  every resolve/rotate audited with actor.

## Evidence (E2E `oyejo_p25`, fresh DB, 44/44 green)

- CLI 14/14: taxonomy + ref format, JSON log line, signature
  dedupe (distinct sites split, same site counts 2), row shape,
  string reports + domain fallback, limiter (2 pass, 3rd denied
  with retry, hashed keys, window expiry), resolve + audit +
  double/missing rejection, rotation (.1/.2 + audit), tail
  whitelist, CLI `show_error` (ref + exit 1), uncaught handler
  (dump + report row), critical alerts once per signature.
- Static map 8/8: all domains wired (database via handler guess).
- HTTP 22/22: bad/missing reset token → 419 (+ref), POST stays
  inline, contact + reset throttles → 429 with Retry-After, no
  raw IPs stored, 500/429/419 pages render safe, desk lists/
  resolves/persists reports, files tail, desk rotation, manager
  + customer 403, rotate cron CLI OK + HTTP 403.
- `php tests/foundation-check.php`: 405/405 (6 new file checks +
  10 new content checks).

## Issues found and fixed (before approval)

1. `err_resolve`/`logs_rotate` audits silently skipped —
   `errors.php` now requires `admin.php` for `adm_audit`.
2. Harness: CLI `ip` is null (assertion used `isset`), `$R`
   variable collision broke the cron test (repo/response rename),
   rerun collided with resolved report + spent quotas —
   clean-room run is 44/44.

No known issues outstanding. Approved for commit under autopilot authority.
