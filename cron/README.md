# Scheduled jobs (skeleton — jobs arrive in Phases 22/24)

- Scripts here run via cron/CLI only, never over HTTP (`cron/` is blocked
  in the root `.htaccess` and guarded by `index.php`).
- Every script must verify `CRON_TOKEN` from `.env` when triggered by URL,
  and refuse to run if it is missing or mismatched.
- Planned jobs: scheduled database backups + cleanup (Phase 24),
  campaign expiry (Phases 19–21).
- Live jobs (Phase 22): `send-notifications.php` (every minute — delivers
  the queued queue with rate limits + retries), `pickup-reminders.php`
  (daily 08:00 — queues tomorrow's pickup reminders).
- Live jobs (Phase 24): `backup.php` (daily 02:00 — full database dump
  to `storage/backups/` + retention cleanup; failures are recorded in
  the `backups` table and alerted via `admin_alert_email`/ops.log).
- Live jobs (Phase 25): `rotate-logs.php` (daily 03:00 — size-based
  rotation of app/mail/sms/whatsapp/ops logs, keeps 5 files each).
- Live jobs (daily earn): `daily-reminders.php` (daily 10:00 — queues
  “streak at risk” messages for customers who checked in yesterday but
  not yet today).

Example cPanel cron (hourly backup check, Phase 24):

    0 * * * * /usr/bin/php /home/USER/public_html/cron/backup.php >> /dev/null 2>&1
