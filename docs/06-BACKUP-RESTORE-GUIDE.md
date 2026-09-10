# Backup & restore guide (Oyejo Gas)

Backups are full database dumps (`.sql.gz`) stored in
`storage/backups/` with unguessable names. The folder is denied over
HTTP, so dumps can only be reached from Admin, cron or the server
filesystem — never by URL.

## Automatic backups

- Cron `cron/backup.php` (daily 02:00 recommended) writes a new dump,
  records it in the `backups` table and deletes files older than the
  retention setting (Admin → Settings → Backups, default 14 days).
- Failures are recorded with status `failed` and mailed to the ops
  alert address (`admin_alert_email`) plus `storage/logs/ops.log`.
- Keep the cron line active (see `05-CPANEL-DEPLOYMENT.md` §4); a
  silent cron means silent backup gaps — the Backups desk shows the
  last success time, check it weekly.

## Manual backups (Admin → Backups)

- **Run now**: creates a dump on demand (needs `backups.create`).
  Use before upgrades, add-on installs and bulk imports.
- **Download**: saves an off-server copy (needs `backups.restore`).
  Download weekly and keep one copy outside the hosting account.
- **Delete**: removes old/failed entries (needs `backups.restore`).
  Retention cleanup handles this automatically; manual delete is for
  one-offs.
- Uploads (`storage/uploads/`) are files, not database rows: back
  them up with your host's file backup or copy the folder via FTP
  monthly (receipts, product images).

## Restore (Admin → Backups, needs `backups.restore`)

1. Pick a backup with status `ok` and click **Restore**.
2. Type the file name to confirm (typed-filename confirmation guards
   against accidental clicks).
3. The desk first takes a **pre-restore snapshot** of the current
   database, then imports the dump. If the import fails, the snapshot
   remains listed so nothing is silently lost.
4. Verify orders, wallet balances and settings after restore; ask
   staff to re-login (sessions may reference changed rows).

## Disaster recovery (server CLI / File Manager)

1. Fresh upload of `oyejo-gas.zip` + `.env` with the new DB
   credentials (or restore `.env` from your password manager —
   never from a public location).
2. Create an empty database and import the latest `.sql.gz`:
   `gunzip -c backup.sql.gz | mysql -u USER -p DBNAME`
   (or cPanel → phpMyAdmin → Import on smaller files).
3. Restore `storage/uploads/` from the file backup.
4. Log in to Admin → Backups and run a fresh backup to confirm the
   chain is healthy again.

## What is NOT in a database backup

- `.env` secrets, uploaded files, add-on folders added later, server
  cron lines. Keep a copy of `.env` values in a password manager and
  re-add the four cron lines on every new server.
