# Phase 24 verification report — Backups, restore, site health

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered

- `includes/ops.php` — pure-PHP MySQL dumps (no mysqldump/exec
  dependency): one statement per line (PDO::quote escapes newlines,
  CREATE TABLE whitespace collapsed), `DROP IF EXISTS` + `CREATE` +
  chunked INSERTs, FK checks off/on, gzip when zlib exists, sha256
  recorded per dump. `ops_backup_run` (manual/scheduled/pre-restore,
  audited, failures recorded + alerted), `ops_backups_list/get`,
  traversal-proof `ops_backup_path`, streaming
  `ops_backup_download`, audited `ops_backup_delete`.
- Restore (`ops_restore`): exact-filename confirmation, checksum
  verification, single-flight lock, pre-restore snapshot first,
  maintenance mode during replay (prior state restored), abort on
  first failing statement. Restored-backup and snapshot rows are
  re-registered after the replay (the dump predates them).
- `ops_cleanup`: deletes backups older than `backup_retention_days`
  (14) while always keeping `backup_keep_min` (3) newest; also
  sweeps old orphan dump files with no DB row.
- `ops_health`: 17 checks (PHP 8.1+, pdo_mysql, curl, mbstring,
  json, DB reachable, core tables, install.lock, APP_KEY,
  debug-off, storage writable, storage .htaccess deny, CRON_TOKEN,
  disk >100MB, backup fresh within `backup_max_age_hours`, queue
  depth <500, 24h notify failures <50). Never throws.
- `ops_alert`: failure alerts to `admin_alert_email` (desk-managed)
  plus `storage/logs/ops.log`.
- `admin/backups.php` desk (`backups.create` to view/run/cleanup,
  `backups.restore` for download/restore/delete): health tab,
  backup history with sizes/rows/status/actor, manual run, cleanup,
  typed-filename restore confirmation, secure downloads, cron
  schedule hint. Console link added.
- `cron/backup.php` (CLI-only, 403 over HTTP): scheduled dump +
  cleanup, exit 1 on failure; `cron/README.md` documents 02:00 run.
- Schema: `backups` table (61 tables); seeds: retention/keep/
  max-age settings.

## Rules enforced (server-side)

- Full-DB downloads and restores need `backups.restore`; restore
  cannot run without the exact filename typed, a valid checksum,
  and a fresh pre-restore snapshot.
- Filenames are regex-locked and realpath-jailed; concurrent
  restores blocked by lock; every mutation audited with actor.

## Evidence (E2E `oyejo_p24`, fresh DB, 29/29 green)

- CLI 12/12: backup recorded (60+ tables, sha matches, audit);
  dump is 142 single-statement lines with CREATE/INSERT/DROP;
  restore roundtrip (marker returns, snapshot kept, maintenance
  restored, row re-registered); wrong-confirm/tamper/missing-id
  rejected; cleanup deletes 1 old keeps 3; 17 unique well-formed
  health checks (fresh-backup passes, debug-on flagged, overall
  reflects it); unwritable-dir failure recorded + ops.log +
  emailed; traversal names rejected; delete removes file+row+
  audit; orphan sweep (old gone, fresh kept).
- HTTP 17/17: health/backups tabs, desk manual run, owner
  download (attachment + gzip magic), customer 403, guest 302,
  missing 404, desk restore wrong/correct confirmation, desk
  delete, desk cleanup, manager desk+download 403, cron CLI OK,
  cron HTTP 403, CSRF rejection.
- `php tests/foundation-check.php`: 389/389 (4 new file checks +
  8 new content checks).

## Issues found and fixed (before approval)

1. New `backups` table reused FK name `fk_backups_user` (owned by
   `backups_log`) — schema import failed with errno 121; renamed
   to `fk_backups_created_by`.
2. Restore replay wiped the restored backup's own row and the
   pre-restore snapshot row (dump predates both) — both are now
   re-registered; cleanup additionally sweeps old orphans.
3. Two same-file parallel edits raced (second clobbered the
   first, leaving `$snap` undefined) — applied sequentially.
4. Harness: `admin_alert_email` is desk-managed (not seeded), so
   the email-alert test now upserts the key; `$R` variable
   collision broke the cron test; `head -8` cut the disposition
   header — all fixed; final run is clean-room green.

No known issues outstanding. Approved for commit under autopilot authority.
